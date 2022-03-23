<?php

declare(strict_types=1);

/**
 * @copyright 2022 Christopher Ng <chrng8@gmail.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\DAV\UserMigration;

use function Safe\sort;
use function Safe\substr;
use OCA\DAV\AppInfo\Application;
use OCA\DAV\CardDAV\CardDavBackend;
use OCA\DAV\CardDAV\Plugin as CardDAVPlugin;
use OCA\DAV\Connector\Sabre\CachingTree;
use OCA\DAV\Connector\Sabre\Server as SabreDavServer;
use OCA\DAV\RootCollection;
use OCP\Contacts\IManager as IContactsManager;
use OCP\Defaults;
use OCP\IL10N;
use OCP\IUser;
use OCP\UserMigration\IExportDestination;
use OCP\UserMigration\IImportSource;
use OCP\UserMigration\IMigrator;
use OCP\UserMigration\TMigratorBasicVersionHandling;
use Sabre\CardDAV\VCFExportPlugin;
use Sabre\VObject\Component\VCard;
use Sabre\VObject\Parser\Parser as VObjectParser;
use Sabre\VObject\Reader as VObjectReader;
use Sabre\VObject\Splitter\VCard as VCardSplitter;
use Sabre\VObject\UUIDUtil;
use Safe\Exceptions\ArrayException;
use Safe\Exceptions\StringsException;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ContactsMigrator implements IMigrator {

	use TMigratorBasicVersionHandling;

	private CardDavBackend $cardDavBackend;

	private IContactsManager $contactsManager;

	// VCFExportPlugin is not to be used as a SabreDAV server plugin
	private VCFExportPlugin $vcfExportPlugin;

	private Defaults $defaults;

	private IL10N $l10n;

	private SabreDavServer $sabreDavServer;

	private const USERS_URI_ROOT = 'principals/users/';

	private const FILENAME_EXT = 'vcf';

	private const METADATA_EXT = 'json';

	private const MIGRATED_URI_PREFIX = 'migrated-';

	private const PATH_ROOT = Application::APP_ID . '/address_books/';

	public function __construct(
		CardDavBackend $cardDavBackend,
		IContactsManager $contactsManager,
		VCFExportPlugin $vcfExportPlugin,
		Defaults $defaults,
		IL10N $l10n
	) {
		$this->cardDavBackend = $cardDavBackend;
		$this->contactsManager = $contactsManager;
		$this->vcfExportPlugin = $vcfExportPlugin;
		$this->defaults = $defaults;
		$this->l10n = $l10n;

		$root = new RootCollection();
		$this->sabreDavServer = new SabreDavServer(new CachingTree($root));
		$this->sabreDavServer->addPlugin(new CardDAVPlugin());
	}

	private function getPrincipalUri(IUser $user): string {
		return ContactsMigrator::USERS_URI_ROOT . $user->getUID();
	}

	/**
	 * @return array{name: string, displayName: string, description: ?string, vCards: VCard[]}
	 *
	 * @throws ContactsMigratorException
	 * @throws InvalidContactException
	 */
	private function getAddressBookExportData(IUser $user, array $addressBookInfo, OutputInterface $output): array {
		$userId = $user->getUID();

		if (empty($addressBookInfo)) {
			throw new ContactsMigratorException("Invalid address book info");
		}

		['uri' => $uri] =  $addressBookInfo;

		$path = CardDAVPlugin::ADDRESSBOOK_ROOT . "/users/$userId/$uri";

		/**
		 * @see \Sabre\CardDAV\VCFExportPlugin::httpGet() implementation reference
		 */

		$cardDataProp = '{' . CardDAVPlugin::NS_CARDDAV . '}address-data';
		$cardNode = $this->sabreDavServer->tree->getNodeForPath($path);
		$nodes = $this->sabreDavServer->getPropertiesIteratorForPath($path, [$cardDataProp], 1);

		/** @var VCard[] $vCards */
		$vCards = [];
		foreach ($nodes as $node) {
			if (isset($node[200][$cardDataProp])) {
				$vCard = VObjectReader::read($node[200][$cardDataProp]);

				$problems = $vCard->validate();
				if (!empty($problems)) {
					$output->writeln('Skipping contact "' . $vCard->{'FN'} . '" containing invalid contact data');
					throw new InvalidContactException();
				}
				$vCards[] = $vCard;
			}
		}

		return [
			'name' => $cardNode->getName(),
			'displayName' => $addressBookInfo['{DAV:}displayname'],
			'description' => $addressBookInfo['{' . CardDAVPlugin::NS_CARDDAV . '}addressbook-description'],
			'vCards' => $vCards,
		];
	}

	/**
	 * @return array<int, array{name: string, displayName: string, description: ?string vCards: VCard[]}>
	 */
	private function getAddressBookExports(IUser $user, OutputInterface $output): array {
		$principalUri = $this->getPrincipalUri($user);

		return array_values(array_filter(array_map(
			function (array $addressBookInfo) use ($user, $output) {
				try {
					return $this->getAddressBookExportData($user, $addressBookInfo, $output);
				} catch (InvalidContactException $e) {
					// Skip export of address books with invalid contacts
					return null;
				}
			},
			$this->cardDavBackend->getAddressBooksForUser($principalUri),
		)));
	}

	private function getUniqueAddressBookUri(IUser $user, string $initialAddressBookUri): string {
		$principalUri = $this->getPrincipalUri($user);

		try {
			$initialAddressBookUri = substr($initialAddressBookUri, 0, strlen(ContactsMigrator::MIGRATED_URI_PREFIX)) === ContactsMigrator::MIGRATED_URI_PREFIX
				? $initialAddressBookUri
				: ContactsMigrator::MIGRATED_URI_PREFIX . $initialAddressBookUri;
		} catch (StringsException $e) {
			throw new ContactsMigratorException('Failed to get unique address book URI', 0, $e);
		}

		$existingAddressBookUris = array_map(
			fn (array $addressBook) => $addressBook['uri'],
			$this->cardDavBackend->getAddressBooksForUser($principalUri),
		);

		$addressBookUri = $initialAddressBookUri;
		$acc = 1;
		while (in_array($addressBookUri, $existingAddressBookUris, true)) {
			$addressBookUri = $initialAddressBookUri . "-$acc";
			++$acc;
		}

		return $addressBookUri;
	}

	/**
	 * @param VCard[] $vCards
	 */
	private function serializeCards(array $vCards) {
		return array_reduce(
			$vCards,
			fn (string $addressBookBlob, VCard $vCard) => $addressBookBlob . $vCard->serialize(),
			'',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function export(IUser $user, IExportDestination $exportDestination, OutputInterface $output): void {
		$output->writeln('Exporting contacts into ' . ContactsMigrator::PATH_ROOT . '…');

		$addressBookExports = $this->getAddressBookExports($user, $output);

		if (empty($addressBookExports)) {
			$output->writeln('No contacts to export…');
		}

		/**
		 * @var string $name
		 * @var string $displayName
		 * @var ?string $description
		 * @var VCard[] $vCards
		 */
		foreach ($addressBookExports as ['name' => $name, 'displayName' => $displayName, 'description' => $description, 'vCards' => $vCards]) {
			// Set filename to sanitized address book name appended with the date
			$basename = preg_replace('/[^a-zA-Z0-9-_ ]/um', '', $name) . '_' . date('Y-m-d');
			$exportPath = ContactsMigrator::PATH_ROOT . $basename . '.' . ContactsMigrator::FILENAME_EXT;
			$metadataExportPath = ContactsMigrator::PATH_ROOT . $basename . '.' . ContactsMigrator::METADATA_EXT;

			if ($exportDestination->addFileContents($exportPath, $this->serializeCards($vCards)) === false) {
				throw new ContactsMigratorException('Could not export address book');
			}

			$metadata = ['displayName' => $displayName, 'description' => $description];
			if ($exportDestination->addFileContents($metadataExportPath, json_encode($metadata)) === false) {
				throw new ContactsMigratorException('Could not export address book metadata');
			}
		}
	}

	/**
	 * @throws InvalidAddressBookException
	 */
	private function importContactObject(int $addressBookId, VCard $vCardObject, OutputInterface $output): void {
		try {
			$this->cardDavBackend->createCard(
				$addressBookId,
				UUIDUtil::getUUID() . ContactsMigrator::FILENAME_EXT,
				$vCardObject->serialize(),
			);
		} catch (Throwable $e) {
			// Rollback creation of address book on error
			$output->writeln('Error creating contact, rolling back creation of address book…');
			$this->cardDavBackend->deleteAddressBook($addressBookId);
			throw new InvalidAddressBookException();
		}
	}

	/**
	 * @param array{displayName: string, description: string} $metadata
	 * @param VCard[] $vCards
	 */
	private function importAddressBook(IUser $user, string $initialAddressBookUri, array $metadata, array $vCards, OutputInterface $output): void {
		$principalUri = $this->getPrincipalUri($user);
		$addressBookUri = $this->getUniqueAddressBookUri($user, $initialAddressBookUri);

		// TODO try alternative implementation https://github.com/nextcloud/server/pull/30963#discussion_r813653699

		$addressBookId = $this->cardDavBackend->createAddressBook($principalUri, $addressBookUri, [
			'{DAV:}displayname' => $metadata['displayName'],
			'{' . CardDAVPlugin::NS_CARDDAV . '}addressbook-description' => $metadata['description'],
		]);

		foreach ($vCards as $vCard) {
			$vCard->PRODID = '-//IDN nextcloud.com//Migrated contact//EN';
			$this->importContactObject($addressBookId, $vCard, $output);
		}
	}

	/**
	 * @return array<int, array{addressBook: string, metadata: string}>
	 */
	private function getAddressBookImports(array $addressBookImportFiles): array {
		$addressBookImports = array_filter(
			$addressBookImportFiles,
			fn (string $filename) => pathinfo($filename, PATHINFO_EXTENSION) === ContactsMigrator::FILENAME_EXT,
		);

		$metadataImports = array_filter(
			$addressBookImportFiles,
			fn (string $filename) => pathinfo($filename, PATHINFO_EXTENSION) === ContactsMigrator::METADATA_EXT,
		);

		try {
			sort($addressBookImports);
			sort($metadataImports);
		} catch (ArrayException $e) {
			throw new ContactsMigratorException('Failed to sort address book files in ' . ContactsMigrator::PATH_ROOT, 0, $e);
		}

		if (count($addressBookImports) !== count($metadataImports)) {
			throw new ContactsMigratorException('Each ' . ContactsMigrator::FILENAME_EXT . ' file must have a corresponding ' . ContactsMigrator::METADATA_EXT . ' file, with identical filenames (excluding the extension)');
		}

		for ($i = 0; $i < count($addressBookImports); ++$i) {
			if (pathinfo($addressBookImports[$i], PATHINFO_FILENAME) !== pathinfo($metadataImports[$i], PATHINFO_FILENAME)) {
				throw new ContactsMigratorException('Each ' . ContactsMigrator::FILENAME_EXT . ' file must have a corresponding ' . ContactsMigrator::METADATA_EXT . ' file, with identical filenames (excluding the extension)');
			}
		}

		return array_map(
			fn (string $addressBookFilename, string $metadataFilename) => ['addressBook' => $addressBookFilename, 'metadata' => $metadataFilename],
			$addressBookImports,
			$metadataImports,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ContactsMigratorException
	 */
	public function import(IUser $user, IImportSource $importSource, OutputInterface $output): void {
		if ($importSource->getMigratorVersion(static::class) === null) {
			$output->writeln('No version for ' . static::class . ', skipping import…');
			return;
		}

		$output->writeln('Importing contacts from ' . ContactsMigrator::PATH_ROOT . '…');

		$addressBookImportFiles = $importSource->getFolderListing(ContactsMigrator::PATH_ROOT);
		if (empty($addressBookImportFiles)) {
			$output->writeln('No contacts to import…');
		}

		foreach ($this->getAddressBookImports($addressBookImportFiles) as ['addressBook' => $addressBookFilename, 'metadata' => $metadataFilename]) {
			$addressBookImportPath = ContactsMigrator::PATH_ROOT . $addressBookFilename;
			$metadataImportPath = ContactsMigrator::PATH_ROOT . $metadataFilename;

			try {
				$vCardSplitter = new VCardSplitter(
					$importSource->getFileAsStream($addressBookImportPath),
					VObjectParser::OPTION_FORGIVING,
				);
			} catch (Throwable $e) {
				throw new ContactsMigratorException("Failed to read file \"$addressBookImportPath\"", 0, $e);
			}

			/** @var VCard[] $vCards */
			$vCards = [];
			/** @var ?VCard $vCard */
			while ($vCard = $vCardSplitter->getNext()) {
				$problems = $vCard->validate();
				if (!empty($problems)) {
					throw new ContactsMigratorException("Invalid address book data contained in \"$addressBookImportPath\"");
				}
				$vCards[] = $vCard;
			}

			$splitFilename = explode('_', $addressBookFilename, 2);
			if (count($splitFilename) !== 2) {
				throw new ContactsMigratorException("Invalid filename \"$addressBookFilename\", expected filename of the format \"<address_book_name>_YYYY-MM-DD." . ContactsMigrator::FILENAME_EXT . '"');
			}
			[$initialAddressBookUri, $suffix] = $splitFilename;

			$metadata = json_decode($importSource->getFileContents($metadataImportPath), true, 512, JSON_THROW_ON_ERROR);

			try {
				$this->importAddressBook(
					$user,
					$initialAddressBookUri,
					$metadata,
					$vCards,
					$output,
				);
			} catch (InvalidAddressBookException $e) {
				// Skip import of current address book on error
				continue;
			}

			foreach ($vCards as $vCard) {
				$vCard->destroy();
			}
		}
	}
}
