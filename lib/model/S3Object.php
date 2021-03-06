<?php



/**
 * Skeleton subclass for representing a row from the 's3object' table.
 *
 * 
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.plugins.cpAwsPropelPlugin.lib.model
 */
class S3Object extends BaseS3Object {

  protected $s3;

  public function getUrl() {
    if (!$this->getPath()) { return null; }
    
    $s3 = $this->getS3();

    return $s3->get_object_url(
      $this->getBucket(),
      $this->getPath(),
      $this->getPreauth());
  }

  public function getBasePath() {
    return '';
  }

  protected function updateFileInfo($path) {
    $this->size = filesize($path);
  }

  /**
   * @see lib/vendor/symfony/lib/plugins/sfDoctrinePlugin/lib/vendor/doctrine/Doctrine/Doctrine_Record::delete()
   */
  public function delete(PropelPDO $con = null) {
    $s3 = $this->getS3();

    $s3->delete_object(
      $this->getBucket(),
      $this->getPath()
    );

    $this->doDelete($s3);
    return parent::delete($con);
  }

  public function upload($file, $s3_path) {
    $s3 = $this->getS3();

    $response = $s3->create_object(
      $this->getBucket(),
      $s3_path,
      array(
        'fileUpload' => $file,
        'acl' => AmazonS3::ACL_PRIVATE,
        'headers' => array(
          'Content-Disposition' => 'attachment; filename="' . $this->getOriginalFilename() . '"'
        )
      )
    );

    if ($response->isOK()) {
      // If the name of the file has changed, delete the old file.
      if ($this->getPath() !== $s3_path) {
        $s3->delete_object(
          $this->getBucket(),
          $this->getPath()
        ); // delete old file

        $this->doDelete($s3);

        $this->setPath($s3_path);
        $this->updateFileInfo($file);
      }

//      $this->doUploadFile($s3, $local_path, $s3_path);
      return $response;
    }
    
    throw new S3_Exception('Check your AWS settings, file was not uploaded successfully.');

  }

  public function generatePath($filename) {
    return $this->sanitize($filename);
  }

  /* FIXME [OP 2012-10-20] This method signature is due to the design of the S3ObjectForm. This method is invoked
     by the form's updateOriginalFilenameColumn method, which is before the form has saved its object. In other words,
     this method assumes that the S3Object will get its original_filename and filename properties set after this method is invoked.
     This logic was designed primarily to handle file uploads via a form, which imposes certain constraints. Primarily the fact
     that the file name is input by the user via the form. This name needs to be sanitized. The other aspect is that the uploaded
     file is only stored temporarily (the whole point being not having to store it permanently on the server).

     In cases where the file is generated by the system, a natural workflow would be to update the S3Object's properties
     based on the document, then invoke this method; in which case one would expect to use the properties of this instance, not
     pass them via method parameters.
  */
  public function uploadFile($original_filename, $local_path, $s3_path) {
    $s3 = $this->getS3();

    $response = $s3->create_object(
      $this->getBucket(),
      $s3_path,
      array(
        'fileUpload' => $local_path,
        'acl' => AmazonS3::ACL_PRIVATE,
        'headers' => array(
          'Content-Disposition' => 'attachment; filename="' . $original_filename . '"'
        )
      )
    );

    if ($response->isOK()) {
      // If the name of the file has changed, delete the old file.
      if ($this->getPath() !== $s3_path) {
        $s3->delete_object(
          $this->getBucket(),
          $this->getPath()
        ); // delete old file

        $this->doDelete($s3);

        $this->setPath($s3_path);
        $this->setOriginalFilename($original_filename);
        $this->updateFileInfo($local_path);

      }

      $this->doUploadFile($s3, $local_path, $s3_path);
      return $original_filename;
    }
    throw new S3_Exception('Check your AWS settings, file was not uploaded successfully.');
  }

  public function downloadFile($dir, $sanitize = false, $filename = null) {
    $path = null;
    $s3 = $this->getS3();

    if ($s3->if_object_exists($this->getBucket(), $this->getPath())) {
      if (!$filename) {
        $filename = ($sanitize ? $this->sanitize($this->getOriginalFilename()) : $this->getOriginalFileName());
      }

      $path = $dir . '/' . $filename;
      
      $s3->get_object(
        $this->getBucket(),
        $this->getPath(),
        array(
          'fileDownload' => $path
        )
      );
    }

    return $path;
  }

  public function save(PropelPDO $con = null) {
    $filename = trim($this->getOriginalFileName());

    if (empty($filename)) {
      return;
    }

    parent::save($con);
  }

  public function sanitize($filename) {
    $s = trim($filename);
    $s = preg_replace("/^[.]*/", "", $s); // removeleading periods
    $s = preg_replace("/[.]*$/", "", $s); // remove trailing periods
    $s = preg_replace("/\.[.]+/", ".", $s); // remove any consecutive periods

    // replace dodhy characters
    $dodgychars = "[^0-9a-zA-Z\\.()_-]"; // allow only alphanumeric, underscore, parentheses, hyphen and period
    $s = preg_replace("/$dodgychars/", "_", $s); // replace dodgy characters

    // replace accented characters
    $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
    $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
    $s = str_replace($a, $b, $s);

    return $s;
  }

  protected function doDelete(AmazonS3 $s3) {}

  protected function doUploadFile(AmazonS3 $s3, $local_path, $s3_path) {}

  public function getS3() {
    if (!$this->s3) {
      $this->s3 = new AmazonS3(array(
        'credentials' => $this->getCredentials()
      ));
    }
    return $this->s3;
  } 
} // S3Object
