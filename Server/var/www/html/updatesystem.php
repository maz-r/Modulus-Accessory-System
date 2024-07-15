<?php
if(isset($_POST["submit"]) && isset($_FILES["fileToUpload"])) 
{
  // delete everything from the uploads directory
  exec ("rm -r /var/www/html/uploads/*");

  // unzip the uploaded file
  $target_dir = "uploads/";
  $target_file = $target_dir . basename($_FILES["fileToUpload"]["name"]);
  $uploadOk = 1;
  $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
  
  if (move_uploaded_file($_FILES["fileToUpload"]["tmp_name"], $target_file)) 
  {
  	exec ("unzip -qqo ".$target_file." -d /var/www/html/uploads/");
	  if (file_exists('/var/www/html/uploads/upgrade.html'))
	  {
//	  $upgrade_text = file_get_contents('/var/www/html/uploads/upgrade.html', FILE_USE_INCLUDE_PATH);
//	  echo $upgrade_text;
 	    if (file_exists('/var/www/html/uploads/install.sh'))
	    {
	      exec ("sudo /var/www/html/uploads/install.sh >/dev/null &");
	      header('Location: /uploads/upgrade.html');
	    }
	  }
	  else
	  {
	    echo "There was a problem with the format of the upgrade file. Please retry."; 
	  }
  } 
  else 
  {
        echo "Sorry, there was an error uploading your file.";
  }
}
else
{
  echo "<h1>Invalid Call; no file specified</h1>";
}
?>
