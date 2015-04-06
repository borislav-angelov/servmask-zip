<?php

// Little-endian byte order

class ServMaskZipArchiver
{
	protected $zipFileName = null;

	protected $zipFileHandler = null;

	protected $centralDirectoryFileName = null;

	protected $centralDirectoryFileHandler = null;

	public function open($fileName, $flag = 'ab') {
		$this->zipFileName = trim($fileName);
		$this->centralDirectoryFileName = sprintf('%sx', $this->zipFileName);

		// Zip file handler
		if (($this->zipFileHandler = fopen($this->zipFileName, 'a+b')) === false) {
			throw new Exception('Unable to open zip file.');
		}

		// Central directory file handler
		if (($this->centralDirectoryFileHandler = fopen($this->centralDirectoryFileName, 'a+b')) === false) {
			throw new Exception('Unable to open zip file.');
		}

	}

	public function add($fileName, $localName = null) {
		if (is_file($fileName)) {
			return $this->addFile($fileName, $localName);
		} else if (is_dir($fileName)) {
			return $this->addDirectory($fileName, $localName);
		}
	}

	protected function addFile($fileName, $localName = null) {
		// Get CRC-32 checksum
		$crc32 = hash_file('crc32b', $fileName); // @TODO: Research for other mechanism because this is not in the PHP core ?
		//$fileHandler = fopen($fileName, 'rb');
		//$crc32 = crc32(fread($fileHandler, filesize($fileName)));
		//fclose($fileHandler);

		// Get file size
		$fileSize = filesize($fileName);

		// Get file offset
		$fileOffset = filesize($this->zipFileName);

		// Sanitize file name
		if ($localName) {
			$fileName = $this->sanitizeFileName($localName);
		} else {
			$fileName = $this->sanitizeFileName($fileName);
		}

		var_dump($fileName);

		// Get file name length
		$fileNameLength = strlen($fileName);

		// Local file header
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
		if (@fwrite($this->zipFileHandler, implode(null, $localFileHeader)) === false) {
			throw new Exception('Unable to write local file header in zip file.');
		}

		// @TODO: Do it in chunks
		// Write file data (variable size)
		if (@fwrite($this->zipFileHandler, file_get_contents($fileName)) === false) {
			throw new Exception('Unable to write file data in zip file.');
		}

		// Add central directory
		$this->addToCentralDirectory($fileName, $fileNameLength, $fileSize, $fileOffset, $crc32, 0x00000000);
	}

	protected function addDirectory($fileName, $localName = null) {

	}

	public function flush() {
		// Add end of central directory
		$this->addEndOfCentralDirectory();

		// Get central direectory file size
		$centralDirectorySize = filesize($this->centralDirectoryFileName);

		// Seek to beginning of central directory file
		if (@rewind($this->centralDirectoryFileHandler) === false) {
			throw new Exception('Unable to seek in central directory file.');
		}

		// Write central directory file in zip file
		if (@fwrite($this->zipFileHandler, fread($this->centralDirectoryFileHandler, $centralDirectorySize)) === false) {
			throw new Exception('Unable to write central directory file in zip file');
		}

		// Close central directory file
		@fclose($this->centralDirectoryFileHandler);

		// Close zip file
		@fclose($this->zipFileHandler);

		// Unlink central directory file
		@unlink($this->centralDirectoryFileName);
	}

	public function close() {
		// Close central directory file
		@fclose($this->centralDirectoryFileHandler);

		// Close zip file
		@fclose($this->zipFileHandler);
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
		if (@fwrite($this->centralDirectoryFileHandler, implode(null, $centralDirectory)) === false) {
			throw new Exception('Unable to write in central directory file.');
		}
	}

	protected function addEndOfCentralDirectory() {
		// Get number of entries
		$numberOfEntries = 2;

		// Get central directory size
		$centralDirectorySize = filesize($this->centralDirectoryFileName);

		// Get central directory offset
		$centralDirectoryOffset = filesize($this->zipFileName);

		// End of central directory structure
		$endOfCentralDirectory = array(
			pack('V', 0x06054b50),              // End of central dir signature (4 bytes)
			pack('v', 0),                       // Number of this disk (2 bytes)
			pack('v', 0),                       // Number of the disk with the start of the central directory (2 bytes)
			pack('v', $numberOfEntries),        // Total number of entries in the central directory on this disk (2 bytes)
			pack('v', $numberOfEntries),        // Total number of entries in the central directory (2 bytes)
			pack('V', $centralDirectorySize),   // Size of the central directory (4 bytes)
			pack('V', $centralDirectoryOffset), // Offset of start of central directory with respect to the starting disk number (4 bytes)
			pack('v', 0),                       // ZIP file comment length (2 bytes)
		);

		// Write end of central directory to file
		if (@fwrite($this->centralDirectoryFileHandler, implode(null, $endOfCentralDirectory)) === false) {
			throw new Exception('Unable to write in central directory file.');
		}
	}

	/**
	 * Sanitize file name
	 *
	 * @param  string $fileName File name
	 * @return string
	 */
	protected function sanitizeFileName($fileName) {
		return preg_replace('/(\\+)|(\/+)/', '/', $fileName);
	}
}