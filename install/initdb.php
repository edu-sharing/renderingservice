<?php
/*
 * $McLicense$
 *
 * $Id$
 *
 */

class initdb
extends Step {

   /* private $scheme = '';
    private $host = '';
    private $base_uri = '';*/
    private $rs_url = '';
    private $base_dir = '';
    private $db_drvr = '';
    private $db_host = '';
    private $db_port = '';
    private $db_name = '';
    private $db_user = '';
    private $db_pass = '';
    private $drop_db = '';
    private $repo_url = '';
    private $repo_host = '';
    private $repo_port = '';
    private $repo_scheme = '';
    private $data_dir = '';
    private $pdo = '';

    // contains create statements of all tables
    private $all_tables = array();

    private $db_srv_version = null;

    /**
     *
     */
    function check($post) {
        if (empty($post['RS_URL'])) {
            $this -> error(initdb_missing_form_data);
            return false;
        }

        $this -> writeLog('_REQUEST', $post);

       /* $this -> scheme = trim($post['URL_SCHEME']);
        $this -> host = trim($post['URL_HOST']);
        $this -> base_uri = $this -> normalizeUri($post['BASE_URI']);*/
        $this -> rs_url = trim($post['RS_URL']);
        $this -> base_dir = $this -> normalizeDir($post['BASE_DIR']);
        $this -> db_drvr = trim($post['DB_DRIVER']);
        $this -> db_host = trim($post['DB_HOST']);
        $this -> db_port = trim($post['DB_PORT']);
        $this -> db_user = trim($post['DB_USER']);
        $this -> db_pass = trim($post['DB_PASS']);
        $this -> db_name = trim($post['DB_NAME']);

        $this -> repo_url = trim($post['REPO_URL']);
        $repo = parse_url($this -> repo_url);
        $this -> repo_host = gethostbyname($repo['host']);
        $this -> repo_scheme = $repo['scheme'];

        if (empty($repo['port'])) {
            $this -> repo_port = '80';
        } else {
            $this -> repo_port = $repo['port'];
        };

        if (!$this -> checkRepoConnection())
            return $this -> error(install_err_fetch_props_homerep);

        $this -> data_dir = trim($post['DATA_DIR']);

        $this -> lang_id = empty($post['DEF_LANG']) ? 1 : intval($_REQUEST['DEF_LANG']);

        try {
            $this -> pdo = new PDO($this -> db_drvr . ':host=' . $this -> db_host . ';port=' . $this -> db_port . ';dbname=' . $this -> db_name, $this -> db_user, $this -> db_pass);
        } catch (PDOException $e) {
            return $this -> error(sprintf($e -> getMessage(), MC_BASE_DIR));
        }

        if (!is_writable(MC_BASE_DIR)) {
            return $this -> error(sprintf(install_err_no_access, MC_BASE_DIR));
        }

        $this -> all_tables = $this -> getAllTables();

        return true;
    }// end method check

    /**
     *
     */
     
    function getUrl() {
        return $this -> rs_url;
    }     
     
    function getScheme() {
        return $this -> scheme;
    }

    function getHost() {
        return $this -> host;
    }

    function getBaseDir() {
        return $this -> base_dir;
    }

    function getRepoUrl() {
        return $this -> repo_url;
    }

    function getRepoHost() {
        return $this -> repo_host;
    }

    function getRepoPort() {
        return $this -> repo_port;
    }

    function getRepoScheme() {
        return $this -> repo_scheme;
    }

    function getDataDir() {
        return $this -> data_dir;
    }

    /**
     *
     */
    function getDbDrvr() {
        return $this -> db_drvr;
    }

    function getDbHost() {
        return $this -> db_host;
    }

    function getDbPort() {
        return $this -> db_port;
    }

    function getDbUser() {
        return $this -> db_user;
    }

    function getDbPass() {
        return $this -> db_pass;
    }

    function getDbName() {
        return $this -> db_name;
    }

    /**
     *
     */
    function getLangId() {
        return $this -> lang_id;
    }

    /**
     *
     */
    function process($post) {
        $dbName = $this -> getDbName();
        $this -> createTables();
        $this -> loadTableContent();
        $this -> writeLog('_REQUEST', $post);
        return true;
    }

    /**
     *
     */
    function getPage($post, $step) {
        $contents = file_get_contents('./_layout/initdb.lay');
        $replace = array('{step}' => $step,
        '{form_name}' => 'set_htaccess', '{form_target}' => '_self', '{form_action}' => 'install.php', '{form_submit}' => install_finish_button_text, '{lang_id}' => INST_LANG_ID, );

        return $this -> replace($contents, $replace);
    }

    /**
     *
     */
    function createTables() {

        $created = 0;
        try {
            foreach ($this -> all_tables as $name => $definition) {
                //drop all tables then create new
                $stm = $this -> pdo -> prepare('DROP TABLE IF EXISTS "' . $name . '"');
                $stm -> execute();
                $stm = $this -> pdo -> prepare($definition);
                $stm -> execute();
                $created++;
            }
            if (!empty($created)) {
                $this -> info(sprintf(install_msg_table_count_create, $created));
            }
            $this -> writeLog('tables_created', $created);
        } catch (PDOException $e) {
            echo $e -> getMessage();
        }

        return true;
    }

    /**
     *
     */
    function loadTableContent() {
        $loaded = 0;
        
        $srcPath = INST_PATH_TMPL . 'sql' . DIRECTORY_SEPARATOR . $this -> getDbDrvr() . DIRECTORY_SEPARATOR;

        foreach ($this -> all_tables as $name => $content) {
            $src = $srcPath . $name . '.sql';
            
            if (!is_file($src)) {
                continue;
            }

            $insert = file_get_contents($src);

            try {
                $stm = $this -> pdo -> prepare($insert);
                $stm -> execute();
                $loaded++;
            } catch (Exception $e) {
                SysMsg::showError("Error on filling DB.");
            }
        }

        if ($loaded) {
            $this -> info(sprintf(install_msg_table_count_load_succeed, $loaded));
        }

        return true;
    }

    /**
     *
     */
    function getAllTables() {
        $srcPath = INST_PATH_TMPL . 'sql' . DIRECTORY_SEPARATOR . $this -> getDbDrvr() . DIRECTORY_SEPARATOR;

        $handle = opendir($srcPath);

        if (!$handle) {
            SysMsg::showError('can not open path "' . $srcPath . '"');
            return false;
        }

        $arrAllTables = array();

        while (($file = readdir($handle)) !== false) {
            $src = $srcPath . $file;

            if (!is_file($src)) {
                continue;
            }

            $base = explode('.', $file);
            if (count($base) != 2) {
                continue;
            }

            if ($base[1] != 'ddl') {
                continue;
            }

            $tableName = $base[0];
            $arrAllTables[$tableName] = file_get_contents($src);
        }

        closedir($handle);

        return $arrAllTables;
    }

    private function checkRepoConnection() {
        require_once (INST_PATH_LIB . 'RemoteAppPropertyHandler.php');
        $remoteAppPropertyHandler = new RemoteAppPropertyHandler($this);
        if (!$remoteAppPropertyHandler -> getHomeRepProperties())
            return false;
        return true;
    }

} // end class initdb
