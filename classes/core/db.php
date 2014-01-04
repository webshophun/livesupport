<?php
    class DB
    {    
        private $mysql;

        /**********************************************************************
        * Creates the connection to the mysql database, selects the posted DB *
        * Returns 0 if unable to connect to the database                      *
        * Returns 2 if the Database does not exist                            *
        * Returns TRUE on success                                             *
        ***********************************************************************/        

        public function __construct()
        {     
            require_once('../../config.php');            
            $this->mysql = @mysql_connect($dbLocalhost, $dbUsername, $dbPassword, true);
            $this->selected_database = @mysql_select_db($dbName, $this->mysql);
            mysql_query('SET NAMES utf8',  $this->mysql);
            mysql_query('SET COLLATE utf8');   
            return TRUE;      
        }      

        public function __destruct()
        {
            @mysql_close($this->mysql) or die(mysql_error());
        }

        public function status()
        {
            if(!$this->mysql)
            {
                return 0;
            }
            if(!$this->selected_database)
            {
                return 2;
            }
            return 1;
        }


        public function query($query,$sor="nincs megadva",$fajl="nincs megadva fájl")
        {
            $this->ellenor_sql($query);
            $sql = mysql_query($query,$this->mysql) or $this->die2($sor,$fajl,$query);
            return TRUE;
        }       

        public function is_table($table)
        {
            $this->ellenor_sql($table);
            if(!mysql_query("SHOW FIELDS FROM ".$table,$this->mysql)){
                return FALSE;
            }else
            {
                return TRUE;
            }
        }

        public function select($query,$sor="nincs megadva",$fajl="nincs megadva fájl")
        {
            $this->ellenor_sql($query);
            $sql = mysql_query($query,$this->mysql) or $this->die2($sor,$fajl,$query);
            $i = 1;
            if(mysql_num_rows($sql) == 0)
            {
                $result = FALSE;
            }
            else
            {
                while($row = mysql_fetch_assoc($sql))
                {
                    foreach($row as $colname => $value)
                    {
                        $result[$i][$colname] = $value;
                    }
                    $i++;
                }
            }
            return $result;
        }

        public function selectRow($query,$sor="nincs megadva",$fajl="nincs megadva fájl")
        {
            $this->ellenor_sql($query);
            $sql = mysql_query($query,$this->mysql) or $this->die2($sor,$fajl,$query);
            if(mysql_num_rows($sql) == 0)
            {
                return FALSE;
            }
            else
            {
                $row = mysql_fetch_array($sql);
                return $row;
            }
        }

        public function selectCell($query,$sor="nincs megadva",$fajl="nincs megadva fájl")
        {
            $this->ellenor_sql($query);
            $sql = mysql_query($query,$this->mysql) or $this->die2($sor,$fajl,$query);
            if(mysql_num_rows($sql) == 0)
            {
                return FALSE;
            }
            else
            {
                $row = mysql_fetch_array($sql);
                return $row['0'];
            }
        }


        public function get_result($query,$sor="nincs megadva",$fajl="nincs megadva fájl")
        {
            $this->ellenor_sql($query);
            $sql = mysql_query($query, $this->mysql) or $this->die2($sor,$fajl,$query);

            return mysql_result($sql, 0);
        }

        function runSQL($file)
        {
            $handle = @fopen($file, "r");
            if ($handle) 
            {
                while(!feof($handle)) 
                {
                    $sql_line[] = fgets($handle);
                }
                fclose($handle);
            }
            else 
            {
                return FALSE;
            }
            foreach ($sql_line as $key => $query) 
            {
                if (trim($query) == "" || strpos ($query, "--") === 0 || strpos ($query, "#") === 0) 
                {
                    unset($sql_line[$key]);
                }
            }
            unset($key, $query);

            foreach ($sql_line as $key => $query) 
            {
                $query = rtrim($query);
                $compare = rtrim($query, ";");
                if ($compare != $query) 
                {
                    $sql_line[$key] = $compare . "|br3ak|";
                }
            }
            unset($key, $query);

            $sql_lines = implode($sql_line);
            $sql_line = explode("|br3ak|", $sql_lines);

            foreach($sql_line as $query)
            {
                if($query)
                {
                    mysql_query($query, $this->mysql) or die("Couldnt Run Query: ".$query."<br />Error: ".mysql_error($this->mysql)."");
                }
            }
            return TRUE;
        }

        public function die2($sor="", $file="", $sql=""){
            $errorVisible=TRUE;;


            //Hiba üzenet: dátum, sql, lap, ip
            $error = date("Y:m:d H:i:s",time())."-kor hiba történt.\nA hibás lekérdezés:\n". $sql ."\nA hibás file:". $file ."\n";
            $error .= "Sor:". $sor ."\nA hiba oka:". mysql_error() ."\n"."\nIp:".getenv('REMOTE_ADDR');

            if($errorVisible == true){
                //A hiba üzenet a képernyore
                die('<br><font color="red">'. $sql ." <br>".mysql_error()."<br> Fájl: <b><i>" . $file . "</i></b> sor: <b><i>" .$sor."</i><b></font>");

            }else{

                //Fájl neve
                $backup_mappa = ROOT."error_log/";
                $dir = dir($backup_mappa);
                $contents = array();
                while ($fil = $dir->read()) {
                    if (!is_dir($backup_mappa. $fil)) {
                        if($fil != "index.php"){
                            $contents[] = $fil;
                        }
                    }
                }
                rsort($contents); //A sorrend megfordítása

                if(count($contents) < 1){ // Ha nincs file
                    //létehozzuk
                    $time = time();
                    $erroFile = $backup_mappa."error_". $time .".txt";
                    $fp = fopen($erroFile,"a");
                    @chmod($fp,0777); //Írható attribútomot adunk neki
                    flock($fp, LOCK_EX);
                    fwrite($fp,$error."\n");
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }else{            
                    //Hiba üzenet beírása
                    $fp = fopen($backup_mappa ."/". $contents[0],"a");
                    flock($fp, LOCK_EX);
                    fwrite($fp,"------------\n". $error ."\n");
                    flock($fp, LOCK_UN);
                    fclose($fp);
                }


                die("<div align='left'>Adatbázis lekérdezési hiba történt, a tulajdonos értesült<br/>a hibáról, amely miatt elnézését kérjük!</div>");
            }
        }// $this->die2 end

        public function ellenor_sql($sql){
            $tiltott_drop  = substr_count(strtolower($sql),"drop table");
            $tiltott_truncat  = substr_count(strtolower($sql),"truncate table");
            if($tiltott_drop > 0 || $tiltott_truncat > 0){
                echo 'Nem törlünk táblát';
                exit;
            }

        }

        //Karakterek levédése
        public function db_in($string) {
            $this->__construct();

            if (function_exists('mysql_real_escape_string')) {
                return mysql_real_escape_string($string);
            } elseif (function_exists('mysql_escape_string')) {
                return mysql_escape_string($string);
            }               

            return addslashes($string);
        }

        public function get_insert_id(){
            return mysql_insert_id($this->mysql);
        }

    }
    $GLOBALS['DB'] =  new DB();
?>