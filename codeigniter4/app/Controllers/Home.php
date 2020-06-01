<?php namespace App\Controllers;

class Home extends BaseController
{
	public function index()
	{
		$db = \Config\Database::connect();
		//print_r($db);
		//return view('welcome_message');
		$query = $db->query('SELECT ID_USUARIO,NOME FROM USUARIOS');
		$results = $query->getResult();
		
		print_r($results);
		
		/*
		foreach ($results as $row)
		{
				echo $row->NOME;
		}
		*/

		echo 'Total Results: ' . count($results);
	}

	//--------------------------------------------------------------------

}
