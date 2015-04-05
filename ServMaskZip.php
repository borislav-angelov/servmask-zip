<?php

// Little-endian byte order

class ServMaskZip
{
	protected $zipFile = null;

	protected $centralDirectory = null;

	public function open($fileName, $flag = 'ab') {
		// Zip File
		$this->zipFile = fopen($fileName, 'a+b');

		// Central Directory
		$this->centralDirectory = fopen(".{$fileName}", 'a+b');
	}

	public function add($fileName, $localName = null) {
		if (is_dir($fileName)) {
			return $this->addDirectory($fileName, $localName);
		}

		return $this->addFile($fileName, $localName);
	}

	protected function addFile($fileName, $localName = null) {
		// Get CRC-32 checksum
		//$crc32 = hash_file('crc32b', $fileName); // @TODO: Research for other mechanism because this is not in the PHP core ?
		$crc32 = crc32(file_get_contents($fileName));

		// Get file size
		$fileSize = filesize($fileName);

		// Get file offset
		$fileOffset = ftell($this->zipFile);

		// Get file name length
		$fileNameLength = strlen($fileName);

		// Local File Header
		$localFileHeader = array(
			pack('V', 0x04034b50),      // Local file header signature (4 bytes)
			pack('v', 20),              // Version needed to extract (2 bytes)
			pack('v', 0),               // General purpose bit flag (2 bytes)
			pack('v', 0),               // Compression method (2 bytes)
			pack('v', 0),               // Last mod file time (2 bytes)
			pack('v', 0),               // Last mod file date (2 bytes)
			pack('V', $crc32),          // CRC-32 (4 bytes)
			pack('V', $fileSize),       // Compressed size (4 bytes)
			pack('V', $fileSize),       // Uncompressed size (4 bytes)
			pack('v', $fileNameLength), // File name length (2 bytes)
			pack('v', 0),               // Extra field length (2 bytes)
			$fileName,                  // File name (variable size)
		);

		// Write local file header
		if (fwrite($this->zipFile, implode(null, $localFileHeader)) === false) {
			throw new Exception('Unable to write local file header in the zip file.');
		}

		// @TODO: Do it in chunks
		// Write file data (variable size)
		if (fwrite($this->zipFile, file_get_contents($fileName)) === false) {
			throw new Exception('Unable to write file data in the zip file.');
		}

		// Add central directory
		$this->addToCentralDirectory($fileName, $fileNameLength, $fileSize, $fileOffset, $crc32, 0x00000000);
	}

	protected function addDirectory($fileName, $localName = null) {

	}

	public function flush() {
		$this->copyCenetralDirectoryToZipFile();
	}

	public function close() {
		// Close central directory
		fclose($this->centralDirectory);

		// Close zip file
		fclose($this->zipFile);
	}

	protected function addToCentralDirectory($fileName, $fileNameLength, $fileSize, $fileOffset, $crc32, $external) {
		// Central directory structure
		$centralDirectory = array(
			pack('V', 0x02014b50),      // Central file header signature (4 bytes)
			pack('v', 0),               // Version made by (2 bytes)
			pack('v', 20),              // Version needed to extract (2 bytes)
			pack('v', 0),               // General purpose bit flag (2 bytes)
			pack('v', 0),               // Compression method (2 bytes)
			pack('v', 0),               // Last mod file time (2 bytes)
			pack('v', 0),               // Last mod file date (2 bytes)
			pack('V', $crc32),          // CRC-32 (4 bytes)
			pack('V', $fileSize),       // Compressed size (4 bytes)
			pack('V', $fileSize),       // Uncompressed size (4 bytes)
			pack('v', $fileNameLength), // File name length (2 bytes)
			pack('v', 0),               // Extra field length (2 bytes)
			pack('v', 0),               // File comment length (2 bytes)
			pack('v', 0),               // Disk number start (2 bytes)
			pack('v', 0),               // Internal file attributes (2 bytes)
			pack('V', $external),       // External file attributes (4 bytes)
			pack('V', $fileOffset),     // Relative offset of local header (4 bytes)
			$fileName,                  // File name (variable size)
		);

		// Write central directory to file
		if (fwrite($this->centralDirectory, implode(null, $centralDirectory)) === false) {
			throw new Exception('Unable to write in the central directory file.');
		}
	}

	protected function endOfCentralDirectory() {
		$numberOfEntries = 1;
		$centralDirectorySize = filesize(stream_get_meta_data($this->centralDirectory)['uri']);
		$centralDirectoryOffset = filesize(stream_get_meta_data($this->zipFile)['uri']);

		// End of central directory structure
		$endOfCentralDirectory = array(
			pack('V', 0x06054b50),              // End of central dir signature (4 bytes)
			pack('v', 0),                       // Number of this disk (2 bytes)
			pack('v', 0),                       // Number of the disk with the start of the central directory (2 bytes)
			pack('v', $numberOfEntries),        // Total number of entries in the central directory on this disk (2 bytes)
			pack('v', $numberOfEntries),        // Total number of entries in the central directory (2 bytes)
			pack('V', $centralDirectorySize),   // Size of the central directory (4 bytes) // @TODO: fix size
			pack('V', $centralDirectoryOffset), // Offset of start of central directory with respect to the starting disk number (4 bytes)
			pack('v', 0),                       // .ZIP file comment length (2 bytes)
		);

		// Write end of central directory to file
		if (fwrite($this->centralDirectory, implode(null, $endOfCentralDirectory)) === false) {
			throw new Exception('Unable to write in the central directory file.');
		}
	}

	protected function copyCenetralDirectoryToZipFile() {
		$this->endOfCentralDirectory();
		rewind($this->centralDirectory);
		fwrite($this->zipFile, fread($this->centralDirectory, filesize(stream_get_meta_data($this->centralDirectory)['uri'])));
	}
}