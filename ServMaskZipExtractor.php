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

		// Set pointer to the central directory
		if (@fseek($this->zipFileHandler, $endOfCentralDirectory['offset']) === -1) {
			throw new Exception('Unable to seek in zip file.');
		}

		$entries = array();

		// Loop over zip entries
		for ($i = 0; $i < $endOfCentralDirectory['entries']; $i++) {

			// Read next 46 bytes
			$data = @fread($this->zipFileHandler, 46);

			// Central directory structure
			$centralDirectory = @unpack('Vid/vversion/vversionExtracted/vflag/vcompression/vmtime/vmdate/Vcrc/VcompressedSize/Vsize/vfileNameLength/vextraLength/vcommentLength/vdisk/vinternal/Vexternal/Voffset', $data);
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

			// Get compression

			// // ----- Look if it is a directory
			// if (substr($p_header['filename'], -1) == '/') {
			//   //$p_header['external'] = 0x41FF0010;
			//   $p_header['external'] = 0x00000010;
			// }

			$entries[] = $centralDirectory;
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
		$endOfCentralDirectory = @unpack('Vid/vdisk/vdiskStart/vdiskEntries/ventries/Vsize/Voffset/vcommentLength', $data);
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