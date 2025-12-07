<?php
/**
 * The Unzipper extracts .zip or .rar archives and .gz files on webservers.
 * Added: Upload ZIP/RAR/GZ support
 */

define('VERSION', '1.0-upload-enabled');

$timestart = microtime(TRUE);
$GLOBALS['status'] = array();

/**
 * ----------------------------------------------------
 * 1. UPLOAD HANDLER (NEW FEATURE)
 * ----------------------------------------------------
 */
if (isset($_POST['upload_zip_btn'])) {

  $uploadDir = './';
  if (!empty($_POST['upload_path'])) {
      $uploadDir .= trim($_POST['upload_path'], '/') . '/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
      }
  }

  if (!empty($_FILES['uploaded_zip']['name'])) {
      $filename = basename($_FILES['uploaded_zip']['name']);
      $target = $uploadDir . $filename;

      if (move_uploaded_file($_FILES['uploaded_zip']['tmp_name'], $target)) {
          $GLOBALS['status'] = ['success' => "File uploaded: $filename"];
      } else {
          $GLOBALS['status'] = ['error' => "File upload failed."];
      }
  } else {
      $GLOBALS['status'] = ['error' => "No file selected."];
  }
}

/**
 * ----------------------------------------------------
 * 2. UNZIPPER ORIGINAL FUNCTIONALITY
 * ----------------------------------------------------
 */
$unzipper = new Unzipper;
if (isset($_POST['dounzip'])) {
  $archive = isset($_POST['zipfile']) ? strip_tags($_POST['zipfile']) : '';
  $destination = isset($_POST['extpath']) ? strip_tags($_POST['extpath']) : '';
  $unzipper->prepareExtraction($archive, $destination);
}

if (isset($_POST['dozip'])) {
  $zippath = !empty($_POST['zippath']) ? strip_tags($_POST['zippath']) : '.';
  $zipfile = 'zipper-' . date("Y-m-d--H-i") . '.zip';
  Zipper::zipDir($zippath, $zipfile);
}

$timeend = microtime(TRUE);
$time = round($timeend - $timestart, 4);

/**
 * ----------------------------------------------------
 * CLASS: Unzipper
 * ----------------------------------------------------
 */
class Unzipper {
  public $localdir = '.';
  public $zipfiles = array();

  public function __construct() {
    if ($dh = opendir($this->localdir)) {
      while (($file = readdir($dh)) !== FALSE) {
        if (in_array(pathinfo($file, PATHINFO_EXTENSION), ['zip','gz','rar'])) {
          $this->zipfiles[] = $file;
        }
      }
      closedir($dh);

      if (!empty($this->zipfiles)) {
        $GLOBALS['status'] = array('info' => 'Archive files ready.');
      } else {
        $GLOBALS['status'] = array('info' => 'No archive files found.');
      }
    }
  }

  public function prepareExtraction($archive, $destination = '') {
    $extpath = empty($destination) ? $this->localdir : $this->localdir . '/' . $destination;
    if (!is_dir($extpath)) mkdir($extpath, 0777, true);
    if (in_array($archive, $this->zipfiles)) self::extract($archive, $extpath);
  }

  public static function extract($archive, $destination) {
    $ext = pathinfo($archive, PATHINFO_EXTENSION);
    switch ($ext) {
      case 'zip': self::extractZipArchive($archive, $destination); break;
      case 'gz': self::extractGzipFile($archive, $destination); break;
      case 'rar': self::extractRarArchive($archive, $destination); break;
    }
  }

  public static function extractZipArchive($archive, $destination) {
    if (!class_exists('ZipArchive')) {
      $GLOBALS['status'] = array('error' => 'ZipArchive not supported');
      return;
    }

    $zip = new ZipArchive;
    if ($zip->open($archive) === TRUE) {
      if (is_writeable($destination . '/')) {
        $zip->extractTo($destination);
        $zip->close();
        $GLOBALS['status'] = array('success' => 'Files unzipped successfully');
      } else {
        $GLOBALS['status'] = array('error' => 'Destination not writable.');
      }
    } else {
      $GLOBALS['status'] = array('error' => 'Cannot read zip archive.');
    }
  }

  public static function extractGzipFile($archive, $destination) {
    if (!function_exists('gzopen')) {
      $GLOBALS['status'] = array('error' => 'gzopen not supported.');
      return;
    }
    $filename = pathinfo($archive, PATHINFO_FILENAME);
    $gzipped = gzopen($archive, "rb");
    $file = fopen($destination . '/' . $filename, "w");
    while ($string = gzread($gzipped, 4096)) fwrite($file, $string);
    gzclose($gzipped);
    fclose($file);
    $GLOBALS['status'] = array('success' => 'GZ extracted.');
  }

  public static function extractRarArchive($archive, $destination) {
    if (!class_exists('RarArchive')) {
      $GLOBALS['status'] = array('error' => 'RAR not supported.');
      return;
    }
    $rar = RarArchive::open($archive);
    if ($rar) {
      if (is_writeable($destination . '/')) {
        foreach ($rar->getEntries() as $entry) $entry->extract($destination);
        $rar->close();
        $GLOBALS['status'] = array('success' => 'RAR extracted.');
      }
    }
  }
}

/**
 * ----------------------------------------------------
 * CLASS: Zipper (UNMODIFIED)
 * ----------------------------------------------------
 */
class Zipper {
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
    $handle = opendir($folder);
    while (FALSE !== $f = readdir($handle)) {
      if ($f != '.' && $f != '..' && $f != basename(__FILE__)) {
        $filePath = "$folder/$f";
        $localPath = substr($filePath, $exclusiveLength);
        if (is_file($filePath)) {
          $zipFile->addFile($filePath, $localPath);
        } elseif (is_dir($filePath)) {
          $zipFile->addEmptyDir($localPath);
          self::folderToZip($filePath, $zipFile, $exclusiveLength);
        }
      }
    }
    closedir($handle);
  }

  public static function zipDir($sourcePath, $outZipPath) {
    $pathInfo = pathinfo($sourcePath);
    $parentPath = $pathInfo['dirname'];
    $dirName = $pathInfo['basename'];

    $z = new ZipArchive();
    $z->open($outZipPath, ZipArchive::CREATE);
    $z->addEmptyDir($dirName);

    if ($sourcePath == $dirName) {
      self::folderToZip($sourcePath, $z, 0);
    } else {
      self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
    }
    $z->close();
    $GLOBALS['status'] = array('success' => 'Created archive ' . $outZipPath);
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>File Manager - Upload & Unzip</title>
  <meta charset="UTF-8">
  <style>
    body { font-family: Arial; line-height: 150%; }
    fieldset { background: #EEE; margin: 20px 0; padding: 10px; border: 0; }
    .submit { padding: 10px 24px; background: #378de5; color: #fff; border:0; }
    .submit:hover { background:#2c6db2; cursor:pointer; }
    .status { padding:10px; background:#EEE; margin-bottom:20px; }
    .status--SUCCESS{ background:green;color:white;font-weight:bold; }
    .status--ERROR{ background:red;color:white;font-weight:bold; }
  </style>
</head>
<body>

<p class="status status--<?php echo strtoupper(key($GLOBALS['status'])); ?>">
  <?php echo reset($GLOBALS['status']); ?><br>
  <small>Processing: <?php echo $time; ?> sec</small>
</p>

<!-- UPLOAD FORM -->
<form action="" method="POST" enctype="multipart/form-data">
  <fieldset>
    <h1>Upload Archive</h1>
    <input type="file" name="uploaded_zip" required><br><br>
    <input type="text" name="upload_path" placeholder="upload path (optional)" class="form-field">
    <br><br>
    <input type="submit" name="upload_zip_btn" value="Upload File" class="submit">
  </fieldset>
</form>

<!-- UNZIP FORM -->
<form action="" method="POST">
  <fieldset>
    <h1>Unzip Archive</h1>
    <select name="zipfile">
      <?php foreach ($unzipper->zipfiles as $zip) echo "<option>$zip</option>"; ?>
    </select>
    <br><br>
    <input type="text" name="extpath" placeholder="extraction path (optional)" class="form-field">
    <br><br>
    <input type="submit" name="dounzip" value="Unzip Archive" class="submit">
  </fieldset>

  <fieldset>
    <h1>Create ZIP</h1>
    <input type="text" name="zippath" placeholder="folder to zip" class="form-field">
    <br><br>
    <input type="submit" name="dozip" value="Create ZIP" class="submit">
  </fieldset>
</form>

</body>
</html>
