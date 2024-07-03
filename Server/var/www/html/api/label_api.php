<?php

$function_type = $_GET["function"];

$db = new SQLite3('data/labels.db');

switch ($function_type)
{
    case 'lookup':
		$query_data = $_GET["data"];
		$stm = $db->prepare('SELECT * FROM labels where switch=?');
		$stm->bindValue(1, $query_data, SQLITE3_TEXT);

		$res = $stm->execute();

		if ($row = $res->fetchArray())
		{
			echo "1,{$row['label']}";
		}
		else
		{
			echo "0";
		}
		break;

    case 'create':
		$stm = $db->prepare("drop table if exists labels");
		$res = $stm->execute();

		$stm = $db->prepare("CREATE TABLE labels (switch TEXT, label TEXT)");
		$res = $stm->execute();

		break;

    case 'add':
		$query_data = $_GET["data"];
        $insert_data = explode(",", $query_data);        

		$stm = $db->prepare('INSERT into labels (switch, label) values (?, ?)');
		$stm->bindValue(1, $insert_data[0], SQLITE3_TEXT);
		$stm->bindValue(2, $insert_data[1], SQLITE3_TEXT);

		$res = $stm->execute();
		break;

	case 'list':
		$stm = $db->prepare('SELECT * FROM labels order by switch');

		$res = $stm->execute();

		$i = 0;
		while ($row = $res->fetchArray())
		{
			if ($i >= 1)
			echo "|";
			echo "{$i},{$row['switch']},{$row['label']}";
			$i++;
		}
		if ($i == 0)
			echo "0";
		break;

	case 'sound_list':
	    $output = null;
		exec ("ls /SoundSystem/Sounds", $output);
	    foreach ($output as $filename)
		  echo "{$filename},";
		break;
		
	case 'sound_delete':
	    $output=null;
        $retval=null;
	    $command = "rm /SoundSystem/Sounds/".$_GET["Filename"];
	    exec ($command, $output, $return_code);
	    echo "$return_code";
	    break;
}
?>
