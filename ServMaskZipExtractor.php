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
		$endStructure = $this->readEndOfCentralDirectory();

		// Set pointer to the central directory
		if (@fseek($this->zipFileHandler, $endStructure['offset']) === -1) {
			throw new Exception('Unable to seek in zip file.');
		}

		$entries = array();

		// Loop over zip entries
		for ($i = 0; $i < $endStructure['entries']; $i++) {

			// Read next 46 bytes
			$data = @fread($this->zipFileHandler, 46);

			// Central directory structure
			$structure = @unpack('Vid/vversion/vversionExtracted/vflag/vcompression/vmtime/vmdate/Vcrc/VcompressedSize/Vsize/vfileNameLength/vextraLength/vcommentLength/vdisk/vinternal/Vexternal/Voffset', $data);
			if ($structure === false) {
				throw new Exception('Unable to unpack zip file.');
			}

			// Verify signature
			if ($structure['id'] !== 0x02014b50) {
				throw new Exception('Invalid signature of zip file. Make sure zip file is created by All in One WP Migration.');
			}

			// Verify compression
			if ($structure['compression'] !== 0) {
				throw new Exception('Unsupported compression method. Make sure zip file is created by All in One WP Migration.');
			}

			// Verify general purpuse big flag
			if (($structure['flag'] & 1) === 1) {
				throw new Exception('Unsupported encryption method. Make sure zip file is created by All in One WP Migration.');
			}

			// Get file name
			if (($fileNameLength = $structure['fileNameLength'])) {
				$structure['fileName'] = fread($this->zipFileHandler, $fileNameLength);
			} else {
				$structure['fileName'] = null;
			}

			// Get extra
			if (($extraLength = $structure['extraLength'])) {
				$structure['extra'] = fread($this->zipFileHandler, $extraLength);
			} else {
				$structure['extra'] = null;
			}

			// Get comment
			if (($commentLength = $structure['commentLength'])) {
				$structure['comment'] = fread($this->zipFileHandler, $commentLength);
			} else {
				$structure['comment'] = null;
			}

			// Get compression

			// // ----- Look if it is a directory
			// if (substr($p_header['filename'], -1) == '/') {
			//   //$p_header['external'] = 0x41FF0010;
			//   $p_header['external'] = 0x00000010;
			// }

			$entries[] = $structure;
		}

		return $entries;
	}

	public function readEndOfCentralDirectory() {
		// Set pointer to the end of central directory (no commentaries)
		if (@fseek($this->zipFileHandler, -22, SEEK_END) === -1) {
			throw new Exception('Unable to seek in zip file.');
		}

		// Read next 22 bytes
		$data = @fread($this->zipFileHandler, 22);

		// End of central dir structure
		$endStructure = @unpack('Vid/vdisk/vdiskStart/vdiskEntries/ventries/Vsize/Voffset/vcommentLength', $data);
		if ($endStructure === false) {
			throw new Exception('Unable to unpack zip file.');
		}

		// Verify signature
		if ($endStructure['id'] !== 0x06054b50) {
			throw new Exception('Invalid signature of zip file. Make sure zip file is created by All in One WP Migration.');
		}

		// Set pointer to the beginning of the file
		@rewind($this->zipFileHandler);

		return $endStructure;
	}
}