<?php

// Little-endian byte order

class ServMaskZipExtractor
{
	protected $zipFileName = null;

	protected $zipFileHandler = null;

	protected $zipFileSize = null;

	public function open($fileName) {
		$this->zipFileName = $fileName;

		// Zip File Handler
		if (($this->zipFileHandler = fopen($fileName, 'rb')) === false) {
			throw new Exception('Unable to open zip file.');
		}

		// Zip File Size
		if (($this->zipFileSize = filesize($fileName)) === false) {
			throw new Exception('Unable to get zip file size.');
		}
	}

	public function readCentralDirectory() {
		$endOfCentralDirectory = $this->readEndOfCentralDirectory();

		// Get offset of end of central directory
		$offsetCentralDirectory = $endOfCentralDirectory['offset'];

		// Loop over zip entries
		for ($i = 0; $i < $endOfCentralDirectory['entries']; $i++) {

			// Set pointer to the central directory
			if (@fseek($this->zipFileHandler, $endOfCentralDirectory['offset']) === -1) {
				throw new Exception('Unable to seek in zip file.');
			}

			// Read next 46 bytes
			$centralDirectoryData = @fread($this->zipFileHandler, 46);

			// Central directory structure
			$centralDirectory = @unpack('Vid/vversion/vversionExtracted/vflag/vcompression/vmtime/vmdate/Vcrc/VcompressedSize/Vsize/vfileNameLength/vextraLength/vcommentLength/vdisk/vinternal/Vexternal/Voffset', $centralDirectoryData);
			if ($centralDirectory === false) {
				throw new Exception('Unable to unpack zip file.');
			}

			// Verify signature
			if ($centralDirectory['id'] !== 0x02014b50) {
				throw new Exception('Invalid signature of zip file. Make sure zip file is created by All in One WP Migration.');
			}

			// Verify compression
			if ($centralDirectory['compression'] !== 0) {
				throw new Exception('Unsupported compression method. Make sure zip file is created by All in One WP Migration.');
			}

			// Verify general purpuse big flag
			if (($centralDirectory['flag'] & 1) === 1) {
				throw new Exception('Unsupported encryption method. Make sure zip file is created by All in One WP Migration.');
			}

			// Get file name
			if (($fileNameLength = $centralDirectory['fileNameLength'])) {
				$centralDirectory['fileName'] = fread($this->zipFileHandler, $fileNameLength);
			} else {
				$centralDirectory['fileName'] = null;
			}

			// Get extra
			if (($extraLength = $centralDirectory['extraLength'])) {
				$centralDirectory['extra'] = fread($this->zipFileHandler, $extraLength);
			} else {
				$centralDirectory['extra'] = null;
			}

			// Get comment
			if (($commentLength = $centralDirectory['commentLength'])) {
				$centralDirectory['comment'] = fread($this->zipFileHandler, $commentLength);
			} else {
				$centralDirectory['comment'] = null;
			}

			// Create directory
			if ((($centralDirectory['external'] & 0x00000010) === 0x00000010) || (substr($centralDirectory['fileName'], -1) === '/')) {
				if (!is_dir($centralDirectory['fileName'])) {
					if (@mkdir($centralDirectory['fileName'], 0755, true) === false) {
						throw new Exception(
							sprintf('Unable to create directory: %s', $centralDirectory['fileName'])
						);
					}
				}
			} else {
				$directory = dirname($centralDirectory['fileName']);
				if (!is_dir($directory)) {
					if (@mkdir($centralDirectory['fileName'], 0755, true) === false) {
						throw new Exception(
							sprintf('Unable to create directory: %s', $centralDirectory['fileName'])
						);
					}
				}

				// Position of the next file in central file directory
				$offsetCentralDirectory = ftell($this->zipFileHandler);

				// Seek to beginning of file header
				if (@fseek($this->zipFileHandler, $centralDirectory['offset']) === -1) {
					throw new Exception('Unable to seek in zip file.');
				}

				// Read next 30 bytes
				$localFileHeaderData = @fread($this->zipFileHandler, 30);

				// Local file header
				$localFileHeader = @unpack('Vid/vversion/vflag/vcompression/vmtime/vmdate/Vcrc/VcompressedSize/Vsize/vfileNameLength/vextraLength', $localFileHeaderData);
				if ($localFileHeader === false) {
					throw new Exception('Unable to unpack zip file.');
				}

				// Verify signature
				if ($localFileHeader['id'] !== 0x04034b50) {
					throw new Exception('Invalid signature of zip file. Make sure zip file is created by All in One WP Migration.');
				}

				// Verify compression
				if ($localFileHeader['compression'] !== 0) {
					throw new Exception('Unsupported compression method. Make sure zip file is created by All in One WP Migration.');
				}

				// Verify general purpuse big flag
				if (($localFileHeader['flag'] & 1) === 1) {
					throw new Exception('Unsupported encryption method. Make sure zip file is created by All in One WP Migration.');
				}

				// Get file name
				if (($fileNameLength = $localFileHeader['fileNameLength'])) {
					$localFileHeader['fileName'] = fread($this->zipFileHandler, $fileNameLength);
				} else {
					$localFileHeader['fileName'] = null;
				}

				// Get extra
				if (($extraLength = $localFileHeader['extraLength'])) {
					$localFileHeader['extra'] = fread($this->zipFileHandler, $extraLength);
				} else {
					$localFileHeader['extra'] = null;
				}

				// Get file data
				$fileData = fread($this->zipFileHandler, $localFileHeader['size']); // Think for central

				// Write file data
				$fileDataHandler = fopen($localFileHeader['fileName'], 'wb');
				fwrite($fileDataHandler, $fileData);
				fclose($fileDataHandler);
			}
		}
	}

	public function readEndOfCentralDirectory() {
		// Set pointer to the end of central directory (no commentaries)
		if (@fseek($this->zipFileHandler, -22, SEEK_END) === -1) {
			throw new Exception('Unable to seek in zip file.');
		}

		// Read next 22 bytes
		$endOfCentralDirectoryData = @fread($this->zipFileHandler, 22);

		// End of central dir structure
		$endOfCentralDirectory = @unpack('Vid/vdisk/vdiskStart/vdiskEntries/ventries/Vsize/Voffset/vcommentLength', $endOfCentralDirectoryData);
		if ($endOfCentralDirectory === false) {
			throw new Exception('Unable to unpack zip file.');
		}

		// Verify signature
		if ($endOfCentralDirectory['id'] !== 0x06054b50) {
			throw new Exception('Invalid signature of zip file. Make sure zip file is created by All in One WP Migration.');
		}

		return $endOfCentralDirectory;
	}
}