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

	public function addFile($fileName, $localName = null) {
		// Get CRC-32 checksum
		//$crc32 = hash_file('crc32b', $fileName); // @TODO: Research for other mechanism because this is not in the PHP core ?
		$crc32 = crc32(file_get_contents($fileName));

		// Get file size
		$fileSize = filesize($fileName);

		// Get file offset
		$fileOffset = ftell($this->zipFile);

		// Get file name length
		$fileNameLength = strlen($fileName);

		// Local file header signature (4 bytes)
		$localFileHeader = "\x50\x4b\x03\x04";

		// Version needed to extract (2 bytes)
		$localFileHeader .= "\x0a\x00";

		// General purpose bit flag (2 bytes)
		$localFileHeader .= "\x00\x00";

		// Compression method (2 bytes)
		$localFileHeader .= "\x00\x00";

		// Last mod file time (2 bytes)
		$localFileHeader .= "\x00\x00";

		// Last mod file date (2 bytes)
		$localFileHeader .= "\x00\x00";

		// CRC-32 (4 bytes)
		$localFileHeader .= pack('V', $crc32);

		// Compressed size (4 bytes)
		$localFileHeader .= pack('V', $fileSize);

		// Uncompressed size (4 bytes)
		$localFileHeader .= pack('V', $fileSize);

		// File name length (2 bytes)
		$localFileHeader .= pack('v', $fileNameLength);

		// Extra field length (2 bytes)
		$localFileHeader .= pack('v', 0);

		// File name (variable size)
		$localFileHeader .= $fileName;

		// File data (variable size)
		$localFileHeader .= file_get_contents($fileName); // @TODO: Do it in chunks

		// Extra field (variable size)
		$localFileHeader .= null;

		// This descriptor MUST exist if bit 3 of the general purpose bit flag is set (optional)
		//
		// Data descriptor - CRC-32 (4 bytes)
		// $localFileHeader .= pack('V', $crc32);
		//
		// Data descriptor - Compressed size (4 bytes)
		// $localFileHeader .= pack('V', $fileSize);
		//
		// Data descriptor - Uncompressed size (4 bytes)
		// $localFileHeader .= pack('V', $fileSize);

		// Write to file
		if (false === fwrite($this->zipFile, $localFileHeader)) {
			throw new Exception('Unable to write in the zip file.');
		}

		// Add central directory
		$this->addToCentralDirectory($fileName, $fileNameLength, $fileSize, $fileOffset, $crc32);
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

	protected function addToCentralDirectory($fileName, $fileNameLength, $fileSize, $fileOffset, $crc32) {
		// Central file header signature (4 bytes)
		$centralDirectory = "\x50\x4b\x01\x02";

		// Version made by (2 bytes)
		$centralDirectory .= "\x00\x00";

		// Version needed to extract (2 bytes)
		$centralDirectory .= "\x0a\x00";

		// General purpose bit flag (2 bytes)
		$centralDirectory .= "\x00\x00";

		// Compression method (2 bytes)
		$centralDirectory .= "\x00\x00";

        // Last mod file time (2 bytes)
        $centralDirectory .= "\x00\x00";

        // Last mod file date (2 bytes)
        $centralDirectory .= "\x00\x00";

		// CRC-32 (4 bytes)
		$centralDirectory .= pack('V', $crc32);

		// Compressed size (4 bytes)
		$centralDirectory .= pack('V', $fileSize);

		// Uncompressed size (4 bytes)
		$centralDirectory .= pack('V', $fileSize);

		// File name length (2 bytes)
		$centralDirectory .= pack('v', $fileNameLength);

		// Extra field length (2 bytes)
		$centralDirectory .= pack('v', 0);

		// File comment length (2 bytes)
		$centralDirectory .= pack('v', 0);

		// Disk number start (2 bytes)
		$centralDirectory .= pack('v', 0);

		// Internal file attributes (2 bytes)
		$centralDirectory .= pack('v', 0);

		// External file attributes (4 bytes)
		$centralDirectory .= pack('V', 32);

		// Relative offset of local header (4 bytes)
		$centralDirectory .= pack('V', $fileOffset);

		// File name (variable size)
		$centralDirectory .= $fileName;

        // Extra field (variable size)
        $centralDirectory .= null;

        // File comment (variable size)
        $centralDirectory .= null;

		// Write to file
		if (false === fwrite($this->centralDirectory, $centralDirectory)) {
			throw new Exception('Unable to write in the central directory file.');
		}
	}

	protected function endOfCentralDirectory() {
		// End of central dir signature (4 bytes)
		$centralDirectory = "\x50\x4b\x05\x06";

		// Number of this disk (2 bytes)
		$centralDirectory .= "\x00\x00";

		// Number of the disk with the start of the central directory (2 bytes)
		$centralDirectory .= "\x00\x00";

		// Total number of entries in the central directory on this disk (2 bytes)
		$centralDirectory .= pack('v', 1); // @TODO: 1 file

		// Total number of entries in the central directory (2 bytes)
		$centralDirectory .= pack('v', 1); // @TODO: 1 file

		// Size of the central directory (4 bytes)
		$centralDirectory .= pack('V', filesize(stream_get_meta_data($this->centralDirectory)['uri'])); // @TODO: fix size

		// Offset of start of central directory with respect to the starting disk number (4 bytes)
		$centralDirectory .= pack('V', filesize(stream_get_meta_data($this->zipFile)['uri'])); // @TODO: fix size

		// .ZIP file comment length (2 bytes)
		$centralDirectory .= "\x00\x00";

		// .ZIP file comment (variable size)
		$centralDirectory .= null;

		// Write to file
		if (false === fwrite($this->centralDirectory, $centralDirectory)) {
			throw new Exception('Unable to write in the central directory file.');
		}

		rewind($this->centralDirectory);
	}

	protected function copyCenetralDirectoryToZipFile() {
		$this->endOfCentralDirectory();
		fwrite($this->zipFile, fread($this->centralDirectory, filesize(stream_get_meta_data($this->centralDirectory)['uri'])));
	}
}