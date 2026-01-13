<?php
/*
 * (c) Paul Isidore A. NIAMIE <paul.isidore@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

//Déclaration des espace de nom
namespace NAbySy ;

mb_internal_encoding('UTF-8');

include_once 'definition_err.php';
include_once 'definition_nabysytype.php';

include_once 'xModuleInfo.php';
include_once 'format.class.php' ;
include_once 'devises.class.php' ;
include_once 'xNabySyCustomListOf.class.php' ;

include_once 'erreur.php' ;
include_once 'notification.class.php';
include_once 'db.class.php' ;

require_once 'auth.class.php';
require_once "vendor/autoload.php";


include_once 'mod_ext/nombre_en_lettre.php' ;
include_once 'mod_ext/rb.php' ;

include_once 'autoload.i.php' ;
include_once 'autoload.class.php' ;
include_once 'log.class.php' ;
include_once 'orm.i.php' ;
include_once 'orm.class.php' ;
include_once 'user.class.php' ;
include_once 'technoweb.i.php';

include_once 'photo.class.php';
include_once 'fileuploader.class.php';

include_once 'observgen.i.php' ;
include_once 'observgen.class.php' ;

include_once 'lib/sms/sms.i.php' ;
include_once 'lib/BonAchatManager/BonAchatManager.i.php';
include_once 'moduleexterne.i.class.php' ;
include_once 'lib/xCurlHelper/xCurlHelper.i.php';
include_once 'lib/ModulePaieManager/ModulePaieManager.i.php';

include_once 'GsModuleManager.class.php' ;
include_once 'GSUrlRouterManager.class.php';
include_once 'xNAbySyApiProxy.class.php';

include_once 'xCacheFileMGR.class.php';

include_once 'startupinfo.php' ;

use DateTime;
use DateTimeZone;
use Exception;
use mysqli;
use NAbySy\GS\Boutique\xBoutique;
use NAbySy\Lib\BonAchat\IBonAchatManager;
use NAbySy\Lib\BonAchat\xBonAchatManager;
use NAbySy\Lib\Http\xCurlHelper;
use NAbySy\Lib\ModuleExterne\IModuleExterne;
use NAbySy\Lib\ModuleExterne\TechnoWEB\ITechnoWEB;
use NAbySy\Lib\ModulePaie\IModulePaieManager;
use NAbySy\Lib\ModulePaie\PaiementModuleLoader;
use NAbySy\Lib\Sms\xSMSEngine;
use NAbySy\OBSERVGEN\xObservGen;
use NAbySy\ModuleMCP;
use NAbySy\ORM\xORMHelper;
use NAbySy\Router\Url\xGSUrlRouterManager;
use NAbySy\Router\Url\xGSUrlRouterResponse;
use NAbySy\xErreur;
use ReflectionObject;
use xNAbySyCustomListOf;

Class xNAbySyGS
{
	public ModuleMCP $MODULE ;
	public static mysqli $db_link ;
	public string $dbase ="" ;
	public string $DataBase="" ;
	public string $MainDataBase="" ;
	public string $db_user ="";
	public string $db_pass="" ;
	public string $db_serveur="" ;
	public int $db_port = 3306 ;
	public $BoutiqueID = 0 ;
	public string $BoutiqueNOM ="" ;

	/**
	 * Retourne la liste des boutiques
	 * @var xBoutique[]
	 */
	public array $Boutiques=[];

	public $Erreur;
	public bool $ISCONNECTED = false ;	
	public $MCP_SEPARATEUR ="" ;
	
	public $ActiveDebug=false ;
	public bool $MODE_INVENTAIRE_BOUTIQUE = false ;
	public bool $MODE_INVENTAIRE_DEPOT = false ;
	/* Gestion de redirection selon heure */
	public $HeureOuverture ;
	public $HeureFermeture ;
	public $SiteFermeture;
	public $SiteOuverture;
	/* ----------------------------- */

	//public $BaseSite='../kssv3/app/web/index.php' ;
	public $BaseSite ;
	public $RacineSite ;

	/**
	 * Sous-Repertoire de stockage des Modules NAbySy-GS
	 */
	public static string $GSSubDirectory="" ;

	public $Template ;
	public xUser $User;

	public $MasterDataBase ="" ;

	public $UserToken ="" ;

	public $IsLinuxOS=true ;
	public $OS_NAME='Linux OS';

	public static xLog $Log ;

	public static $ListeModuleAutoLoader=[];
	public static array $ListeModuleRH ;
	public static array $ListeModuleRS ;
	public static array $ListeModuleExterne ;

	public static array $ListeModuleObserv=[] ;

	/** Contient la liste des tous les Modules de Réduction notemment le module principal de Bon d'Achat */
	public static array $ListeModuleBonAchat=[];

	//public static xSiege $Siege ;

	public static xSMSEngine $SMSEngine ;

	public static xBonAchatManager $BonAchatManager ;

	public static xCurlHelper $CURL ;

	public const GLOBAL_AUTO_CREATE_DBTABLE=true ; //Mettez True si vous etes en mode de developpement;

	public const TEST_MODE=false;	//False pour le mode production

	//La boutique par défaut ou la boutique en cour d'utilisation
	public ?xBoutique $MaBoutique ;

	/** Identifiant du Poste de Saisie utilisé par l'utilisateur */
	public int $IdPosteClient = 0 ;

	/** Nom du Poste de Saisie utilisé par l'utilisateur */
	public string $NomPosteClient = "POS_PLATEFORME" ;

	/**
	 * Liste des Modules de Paiement Présent
	 * @var IModulePaieManager[]
	 */
	public static $ListeModulePaiement =[] ;

	public static xNAbySyGS $Main ;

	public static xGSModuleManager $GSModManager ;

	public static $RequetteToIgnoreInLOG=[] ;

	/**
	 * Si vrai, le module 'authentification de NAbySyGS enverra la reponse d'authentification
	 * @var bool
	 */
	public static bool $SendAuthReponse=false ;

	/**
	 * La durée d'une session utilisateur en seconde. 3 heures Par défaut
	 * 3600 secondes X 3h = 10 800 (3heures)
	 * @var int
	 */
	public static int $AUTH_DUREE_SESSION = 3600 * 3 ;

	/**
	 * Si fournit, la variable sera compléter à la racine du site web qui heberge NAbySY
	 * @var null|string
	 */
	public static ?string $BASEDIR = null ;

	/**
	 * Id de la boutique chargée de l'authentification des Utilisateurs
	 * @var null|int
	 */
	public static ?int $AUTH_BOUTIQUE_ID = null ;

	public bool $DesableTokenCheckingOnLoad = true ;

	public static ?xNAbySyGS $ParentNAbySy = null ;

	public static xGSUrlRouterManager $UrlRouter ;

	/**
	 * Si oui, le Routage par URL sera actif même si on n'appel pas la méthode
	 * N::ReadUrlRouterRequest();
	 * @var bool
	 */
	public static bool $ActiveUrlRouter = true ;

	/**
	 * Par défaut, si aucune route par URL trouvé, Les routes par paramètre (Méthode NAbySy NAtif) seront vérifiées
	 * @var bool
	 */
	public static bool $ByPasseNoUrlRoute = true;

	/** Paramétrage de l'etablissement */
	public ?xORMHelper $Parametre ;

	public static ITechnoWEB |null $TechnoWEBMgr ;

	/**
	 * Dossier de Travail de la base de donnée en cour
	 * @var string
	 */
	public string $RepWork = '' ;

	/**
	 * Liste des Client PAM / Boutiques chargée dans le cache
	 * @var array
	 */
	public static $ListeBoutique=[];

	/**
	 * Si VRAI, Le module d'authentification ignorera l'authentification de l'Utilisateur.
	 * Utile pour l'affichage des données d'une Boutique en Ligne. Activez cette optionuniquement si les utilisateurs
	 * doivent être authentifiés.
	 * @var bool
	 */
	public static bool $NO_AUTH = false;

	public function __construct($Myserveur,$Myuser,$Mypasswd,ModuleMCP $mod,$db,$MasterDB="nabysygs", int $port=3306, 
		string $baseDir=null, ?bool $desableTokenAuth=true)
	{ 
		self::$Main = $this ;
		if(isset($baseDir)){
			if( $baseDir !==''){
				self::$BASEDIR = $baseDir ;
			}
		}
		if (!isset(self::$Log)){
			$Dt=date('mY') ;
			self::$Log=new xLog($this,"NAbySyGS_Log-".$Dt.".csv") ;
			//self::$Log->Write("Chargement du Module Principal NAbySy RH-RS");
		}
		if(isset($desableTokenAuth)){
			$this->DesableTokenCheckingOnLoad = $desableTokenAuth ;
		}
		$Chemin=explode("/",$_SERVER['REQUEST_URI']) ;
		$this->RacineSite=$Chemin[1] ;
		$this->BaseSite='/'.$Chemin[1].'/app/web/index.php' ;

		$this->MCP_SEPARATEUR="*--*" ;
		$this->MODULE = $mod;
		$this->MODULE->Version =self::VERSION() ;
		$this->dbase = $db ;
		$this->DataBase=$db ;
		$this->MainDataBase=$MasterDB ;
		$this->MasterDataBase=$this->MainDataBase ;
		$this->MODE_INVENTAIRE_BOUTIQUE=false ;
		$this->MODE_INVENTAIRE_DEPOT=false ;

		self::$RequetteToIgnoreInLOG = [];
		self::$RequetteToIgnoreInLOG[]='SELECT';
		self::$RequetteToIgnoreInLOG[]='ALTER TABLE';
				
		$this->OS_NAME=PHP_OS;
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			$this->IsLinuxOS=false;
		} else {
			$this->IsLinuxOS=true;
		}
		/* Correction du Fuseau Horraire */
		//echo date_default_timezone_get();
		date_default_timezone_set("Africa/Dakar");

		$phpVersion=explode('.',phpversion());
		if (count($phpVersion)){
			if ((int)$phpVersion[0]<8){
				$Err=new xErreur;
				$Err->OK=0;
				$Err->TxErreur = "La version PHP ".phpversion()." n'est pas prise en charge. Migrer vers la version 8.2.0 au minimum.";
				echo json_encode($Err);
				exit;
			}
			if ((int)$phpVersion[1]<2){
				$Err=new xErreur;
				$Err->OK=0;
				$Err->TxErreur = "La version PHP ".phpversion()." n'est pas prise en charge. Migrer vers la version 8.2.0 au minimum.";
				echo json_encode($Err);
				exit;
			}
		}
		
		if(!isset(self::$db_link) || $Myserveur !== $this->db_serveur ){
			if($port <= 0){
				$port=3306;
			}
			self::$db_link = new mysqli($Myserveur, $Myuser, $Mypasswd, $db,$port) or die("Error ".mysqli_error(self::$db_link )); // mysql_connect($serveur,$user,$passwd);                        // connection serveur
			if (!self::$db_link){
				echo $this->MODULE->Nom."Connexion impossible a la base de donnée sur ".$Myserveur." :user=".$Myuser."\n";
				echo "<td><div align='center'><a href=./ />Retourner à l'acceuil SVP !</a></div></td>";
				$this->Erreur=mysqli_error(self::$db_link ) ;
				$this->ISCONNECTED=false ;
				return;
			}
			$this->db_port=$port ;

			$this->Erreur="" ;
			$this->ISCONNECTED=true ;
			$this->db_user=$Myuser ;
			$this->db_pass=$Mypasswd ;
			$this->db_serveur=$Myserveur ;
			$this->UserToken=null ;

			if ($this->IsLinuxOS){
				try{
					//self::$db_link->query("SET time_zone = 'Africa/Dakar'");
				}catch(Exception $ex){
					// A cause des serveur MySQL de ONET SOLUTIOn qui utilise que MySQL d'Oracle
					self::$Log->Write($ex->getMessage());
				}				
			}

			$MAJ=$this->MAJ_DB() ;
			
			self::$CURL=new xCurlHelper($this);

			/* Chargement des Modules avec Autoload 				
				Autoload des interfaces/class du dossier rh
					Autoload des interfaces (sous dossier rh)
				Autoload des interfaces/class du dossier rs
					Autoload des interfaces (sous dossier rs)
			*/
			if(!is_dir(self::CurrentFolder()."gs" )){
				try {
					mkdir(self::CurrentFolder()."gs", 0777, true);
				} catch (\Throwable $th) {
					//throw $th;
					if(!is_dir(self::CurrentFolder()."gs" )){
						self::$Log->Write("Impossible de créer le dossier gs dans ".self::CurrentFolder()."gs");
						$Err=new xErreur();
						$Err->OK=0;
						$Err->TxErreur="Impossible de créer le dossier gs dans ".self::CurrentFolder()."gs";
						echo json_encode($Err);
						exit;
					}
				}
			}

			self::LoadModuleLib();
						
			self::LoadModuleGS();

			$this->ChargeInfos() ;

			self::LoadExternalModuleLib();

			$PMLoader=new PaiementModuleLoader($this); // PaiementModuleLoader($this); //Chargement automatique des Modules de paiements disponible

			/* Modification du jeu de résultats en utf8mb4 */
			self::$db_link->set_charset("utf8mb4");
			//printf("Jeu de caractère en cour : %s\n", self::$db_link->character_set_name());

			//$this->AddToJournal("SYSTEME",0,"DEBUG","Prêt pour les opérations !") ;
			
			
			
			if($this->MaBoutique->DataBase !== $this->DataBase){
				$Lst=$this->MaBoutique->ChargeListe("dbname like '".$this->DataBase."'") ;
				if($Lst->num_rows>0){
					$rw=$Lst->fetch_array() ;
					$IdB=0;
					if(isset($rw['id'])){
						$IdB=$rw['id'] ;
					}elseif(isset($rw['ID'])){
						$IdB=$rw['ID'] ;
					}elseif(isset($rw['Id'])){
						$IdB=$rw['Id'] ;
					}
					$Bout=new xBoutique($this,$IdB) ;
					$this->MaBoutique=$Bout ;
				}
			}			
			
			self::$SMSEngine=new xSMSEngine($this);
			
			self::$BonAchatManager=new xBonAchatManager($this);			

			foreach (self::$ListeModuleAutoLoader as $AutoLoad){
				//On chargera uniquement les Observateurs
				$CheckObs='xObserv';
				$Len=strlen($CheckObs) ;
				foreach ($AutoLoad->ListeModule as $Mod){
					$Pref=substr($Mod[0],0,$Len) ;
					if (strtolower($Pref)==strtolower($CheckObs)){
						$ClassN=$Mod[0] ;
						$NewObserv=new $ClassN($this);
					}
				}
			}

			self::$GSModManager = new xGSModuleManager(self::$Main);

			$this->ReadConfig() ;

			self::$Main = $this ;

			self::$UrlRouter = new xGSUrlRouterManager($this);
		}

	}

	public static function initializeTechnoWEB(ITechnoWEB $manager): void {
        self::$TechnoWEBMgr = $manager;
		if(isset(self::$TechnoWEBMgr)){
			if(isset($_REQUEST['IDTECHNOWEB'])){
				$nabysy = self::getInstance(null,true);
				//self::$Log->Write("TechnoWeb: Boutique Prec: ".$nabysy->MaBoutique->Nom,1) ;
				//self::$Log->Write("TechnoWeb: Base de donnée Précédent: ".$nabysy->MaBoutique->DataBase,0) ;
				$nabysy->LoadDataBaseFromTechnoWEBClient();
				//self::$Log->Write("TechnoWeb: Base de donnée du Client: ".$nabysy->MaBoutique->DataBase,1) ;
				//echo("Base de donnée du Client: ".self::getInstance()->MaBoutique->DataBase)."</br>";//exit;
				// self::LoadModuleLib();
				// self::LoadModuleGS();
				$nabysy::LoadModuleLib(2);
				//self::$Log->Write("TechnoWeb: BD Après LoadModuleLib: ".$nabysy->MaBoutique->DataBase,1) ;
				$nabysy::LoadModuleGS();
				//self::$Log->Write("TechnoWeb: BD Après LoadModuleGS: ".$nabysy->MaBoutique->DataBase,1) ;

				//self::$Log->Write("TechnoWeb: BD Avant Refresh Parametre: ".$nabysy->MaBoutique->Parametre->DataBase,1) ;
				$nabysy->MaBoutique->Parametre = new xORMHelper($nabysy,1,true,'parametre');
				//self::$Main->ChargeInfos() ;
				self::$Main = $nabysy ;
				//self::$Log->Write("TechnoWeb BD Après Refresh Parametre: ".$nabysy->MaBoutique->Parametre->DataBase, 1) ;
				$nabysy->MaBoutique = $nabysy->MaBoutique->Parametre->Main->MaBoutique ;
				//self::$Log->Write("BOUTIQUE NNN: ".$nabysy->MaBoutique->Nom);
				//self::$Log->Write("BOUTIQUE III: ".$nabysy->MaBoutique->DataBase);
				//self::$Log->Write("TechnoWeb: Resolution terminé Base de donnée du Client: ".$nabysy->MaBoutique->DataBase, 1) ;
			}
		}
    }

	/**
	 * Convertit un texte en carmel case
	 * @param string $text 
	 * @return string 
	 */
	public static function toCamelCase(string $text): string {
		// 1. Remplacer les tirets (-) et les espaces ( ) par un espace standard (pour ucwords).
		$text = str_replace(['-', ' '], ' ', $text);
		
		// 2. Mettre en majuscule la première lettre de chaque mot.
		// NOTE : ucwords() est sensible à la locale et peut nécessiter mb_convert_case(MB_CASE_TITLE) 
		// si vous travaillez avec des caractères spéciaux, mais la version simple suffit ici.
		$text = ucwords($text);
		
		// 3. Supprimer tous les espaces restants.
		$text = str_replace(' ', '', $text);

		// 4. Mettre la première lettre de la chaîne résultante en minuscule (si vous voulez du 'lowerCamelCase').
		// Si vous voulez garder la première lettre en majuscule (UpperCamelCase/PascalCase), vous pouvez sauter cette étape.
		// $text = lcfirst($text); 
		
		return $text;
	}

	public function ChargeInfos(xBoutique $StartBoutique=null){
		$postData=$this->ConvertBodyPostToArray();
		$PARAM=$_REQUEST;
		//On charge la boutique par défaut qui est le Depot
		//self::$Log->Write("Chargement des Données du Dépot...");
		//var_dump($PARAM);//exit;
		if(isset($StartBoutique)){
			$this->MaBoutique=$StartBoutique ;
		}else{
			$this->MaBoutique=new xBoutique($this,0);
			$Depot=$this->MaBoutique->GetDepot();
			//var_dump($Depot);
			if (isset($Depot)){
				if ($Depot->Id>0){
					$this->MaBoutique=$Depot;
					$CanAdd=true;
					if(count(self::$ListeBoutique)){
						foreach (self::$ListeBoutique as $Bout) {
							if($Bout->Id == $Depot->Id){
								$CanAdd=false;
								break;
							}
						}
					}
					if($CanAdd){
						self::$ListeBoutique[]=$Depot ;
					}
				}
			}
		}

		if($this->MaBoutique->Id == 0){
			$this->MaBoutique->IdCompteClient=0;
			$this->MaBoutique->Nom = $this->MODULE->MCP_CLIENT;
			$this->MaBoutique->Serveur = $this->db_serveur;
			$this->MaBoutique->DBName = $this->DataBase;
			$this->MaBoutique->DBUser = $this->db_user;
			$this->MaBoutique->DBPassword = $this->db_pass;
			$this->MaBoutique->ACTIF = 1;
			$this->MaBoutique->IMP_LIGNE="";
			$this->MaBoutique->IsBoutique=0; //Depôt Mère
			$this->MaBoutique->Enregistrer();
		}
		
		//$LstB=$this->MaBoutique->ChargeListe("ID <> ".$Depot->Id,null,"ID");
		$TxSQL = "select ID from `".$this->MasterDataBase."`.boutique where ID<>0 ";
		$LstB = $this->ReadWrite($TxSQL);
		
		if($LstB->num_rows){
			while($rw = $LstB->fetch_assoc()){
				//$Bout = new xBoutique($this,(int)$rw['ID'],false);
				if((int)$rw['ID']){
					$Bout = new xBoutique($this,(int)$rw['ID'],false);
					$this->Boutiques[] = $Bout ;
					$CanAdd=true;
					if(count(self::$ListeBoutique)){
						foreach (self::$ListeBoutique as $BoutP) {
							if($Bout->Id == $BoutP->Id){
								$CanAdd=false;
								break;
							}
						}
					}
					if($CanAdd){
						//var_dump("Ajout de ".$Bout->Nom);
						self::$ListeBoutique[] = $Bout ;
					}
				}
			}
		}

		if (isset($_REQUEST['TOKEN'])){
			$_REQUEST['Token']=$_REQUEST['TOKEN'] ;
		}
		if (isset($_REQUEST['IDPOSTE'])){
			$this->IdPosteClient=$_REQUEST['IDPOSTE'] ;
		}
		if (isset($_REQUEST['IdPoste'])){
			$this->IdPosteClient=$_REQUEST['IdPoste'] ;
		}
		if (isset($_REQUEST['NOMPOSTE'])){
			$this->NomPosteClient=$_REQUEST['NOMPOSTE'] ;
		}
		if (isset($_REQUEST['NomPoste'])){
			$this->NomPosteClient=$_REQUEST['NomPoste'] ;
		}

		$IdUtilisateur=null;
		
		if (isset($_REQUEST['Token']) && !$this->DesableTokenCheckingOnLoad){
			$Auth=new xAuth($this) ;
			//echo 'je cherche ici NAbySyMain...' ;
			$Usr=$Auth->DecodeToken($_REQUEST['Token']);
			
			if (isset($Usr)){
				$TxClass=get_class($Usr);
				if ($TxClass !== 'xErreur' && $TxClass !== 'NAbySy\xErreur'){
					if(is_string($Usr->user_data)){
						try {
							$vUsrD = json_decode($Usr->user_data);
							if($vUsrD){
								$Usr->user_data = $vUsrD ;
								//var_dump($Usr);
								//exit;
							}
						} catch (\Throwable $th) {
							//throw $th;
						}
					}
					$IdUtilisateur=$Usr->user_id ;
					$this->IdPosteClient=$Usr->IdPoste;
					$this->NomPosteClient=$Usr->NomPoste;
				}else{
					echo json_encode($Usr) ;
					exit ;
				}
			}
			
		}

		if (isset($IdUtilisateur)){
			$IdUser=$IdUtilisateur ;
			$this->User=new xUser($this,$IdUser,self::GLOBAL_AUTO_CREATE_DBTABLE,null) ;
			$Auth=new xAuth($this) ;
			$this->UserToken=$Auth->GetToken($this->User);
		}
		
		if (isset($_REQUEST['IdUser'])){
			$IdUser=$_REQUEST['IdUser'] ;
			$this->User=new xUser($this,$IdUser) ;
			$Auth=new xAuth($this) ;
			$this->UserToken=$Auth->GetToken($this->User);
		}
		
		$this->Parametre = new xORMHelper($this,1,true,'parametre');
	}		
	

	public function SelectDB($base){
		//global $serveur,$user,$passwd,$bdd,$db_link, $MODULE ;
		if (!isset(self::$db_link)){
			//self::$db_link = mysql_connect($serveur,$user,$passwd);                        // connection serveur
			if (!self::$db_link){
				echo $this->MODULE->Nom."Connexion impossible a la base de donnée sur ".$this->db_serveur." :user=".$this->db_user."\n";
				exit;
			}
		}
		self::$db_link->select_db($base);
		return true;
	}

	public function SelectBoutique(int $IdBoutique){
		$xBoutique=new xBoutique($this,$IdBoutique) ;
		if (isset($xBoutique)){
			if ($xBoutique->Id>0){
				$this->MaBoutique=$xBoutique ;
				$this->SelectDB($xBoutique->DBName) ;
				return true ;
			}
		}
		return false ;
	}

	public static function SelectDefautBoutique(int $IdBoutique){
		$xBoutique=new xBoutique(self::$Main,$IdBoutique) ;
		if (isset($xBoutique)){
			if ($xBoutique->Id>0){
				self::$Main->MaBoutique=$xBoutique ;
				self::$Main->SelectDB($xBoutique->DBName) ;
				return true ;
			}
		}
		return false ;
	}

	/**
	 * Retourne la Boutique ayant le Id = IdBoutique.
	 * Si IdBoutique = 0, La fonction retourne le dépôt
	 * @param int $IdBoutique 
	 * @return null|xBoutique 
	 * @throws Exception 
	 */
	public static function GetBoutiqueByID(int $IdBoutique):null|xBoutique{
		$nab=self::getInstance();
		if(isset(self::$ParentNAbySy)){
			$nab = self::$ParentNAbySy ;
		}
		$Bout=$nab->GetBoutique($IdBoutique);
		return $Bout ;
	}

	public function ReadSQLArray($SQL,$Debug=true){
		$MySQLReponse=$this->ReadWrite($SQL,null,null,$Debug) ;
		$Reponse=self::EncodeReponseSQL($MySQLReponse) ;
		return $Reponse ;
	}

	public function ReadWrite($SQL,$NoReponse=false,$InsertTable=null,$DEBUG=true){
		$IsOK=false ;
		//global $serveur,$user,$passwd,$bdd,$db_link, $MODULE ;
		//$this->SelectDB();
		$TxBout="MAIN" ;
		if (isset($this->MaBoutique)){
			$TxBout=$this->MaBoutique->DataBase ;
		}
		$logFolder=self::CurrentFolder(true).'log';
		if (!is_dir($logFolder)){
			try{
				mkdir($logFolder,0777,true) ;
			}catch (Exception $e){
				$Dat=date("Y-m-d");
				$Tim=date("H:i:s");
				$FicheExecption=$logFolder."/SysFileError".$TxBout.date('mY').".csv" ;
				$F= fopen($FicheExecption, 'a');			
				$TxT=$Dat.";".$Tim.";".$e->getMessage().";" ;
				$TxT .="\r\n" ;				
				fputs($F, $TxT);
				fclose($F);
				//echo 'Erreur: '.$e->getMessage();
				exit;
			}
		}

		$Fichier=$logFolder."/DebugLOG".$TxBout.date('mY').".csv" ;
		$FichierError=$logFolder."/DebugLOGError".$TxBout.date('mY').".txt" ;	

		$req=null;
		try{
			//$vSQL=self::$db_link->real_escape_string($SQL) ;
			$req=self::$db_link->query($SQL);
			$ResultatMySQLi=$req ;

		} catch(Exception $e) {
			$error=$e->getMessage() ;
			$Dat=date("Y-m-d");
			$Tim=date("H:i:s");
			$FicheExecption=$logFolder."/SQLiException".$TxBout.date('mY').".csv" ;
			$F= fopen($FicheExecption, 'a');			
			$TxT=$Dat.";".$Tim.";".$error.";" ;
			$TxT .="\r\n" ;	
			$TxT .="REQUETTE SOURCE: ".$SQL."\r\n" ;				
			fputs($F, $TxT);
			fclose($F);

			if($this->ActiveDebug){
				header('Content-Type: application/json');
				echo json_encode(["error" => "SQL Error: " . $e->getMessage(), "sql" => $SQL]);
				exit;
			}
		}		

		if (!$req) {
			
			$Dat=date("Y-m-d");
			$Tim=date("H:i:s");
			$IpClient=$_SERVER['REMOTE_ADDR'];
			$PortClient=$_SERVER['REMOTE_PORT'];
			//$NomBoutique=$this->MaBoutique->Nom ;
			$Tache ="ERREUR SYSTEME - READWRITE SQL" ;
			$Note= self::$db_link->error ;
			$Fichier=$FichierError ;
			// 1 : on ouvre le fichier
			$monfichier = fopen($Fichier, 'a');
				$TxT=$Dat.";".$Tim.";".$SQL.";".self::$db_link->error ;
				$TxLog=str_replace("\n","",$TxT) ;
				$TxLog=str_replace("\r\n","",$TxLog) ;
				$TxLog=str_replace("\r","",$TxLog) ;
				$TxT=$Dat.";".$Tim.";".$TxLog."\r\n" ;				
				fputs($monfichier, $TxT);
				// 2 : on fera ici nos opérations sur le fichier...
				// 3 : quand on a fini de l'utiliser, on ferme le fichier
			fclose($monfichier);

			//En cas d'erreur on l'inscrit dans le journal systeme
			$ChampDate="DateEnreg" ;
			$ChampHeure="HeureEnreg" ;
			$ChampOperation = "OPERATION" ;
			$MyDB=new xDB($this) ;
			if(!$MyDB->ChampsExiste("journal",$ChampDate,$this->MainDataBase)){
				if($MyDB->ChampsExiste("journal","Date",$this->MainDataBase)){
					$ChampDate="Date" ;
					$ChampHeure="Heure" ;
					$ChampOperation = "Tache" ;
				}
			}
			if(!$MyDB->ChampsExiste("journal","IdUtilisateur",$this->MainDataBase)){
				$MyDB->AlterTable("journal","IdUtilisateur","VARCHAR(255)","ADD","0",$this->MainDataBase) ;
			}
			
			$ErrSQL="insert into ".$this->MasterDataBase.".journal (`".$ChampDate."`,`".$ChampHeure."`,`".$ChampOperation."`,`NOTE`) values(
			'".$Dat."','".$Tim."','".$Tache."','".$Note."')" ;
			$OkErr=self::$db_link->query($ErrSQL);
			if (!$OkErr){
				echo "<h1>Erreur Critique dans le module NAbySyGS, contacter l'administrateur svp !</br>".
				self::$db_link->error."</br></h1>" ;
				exit ;
			}
		}
		//$req=mysql_query($SQL,self::$db_link) ;
		if (!$req)
				{
					echo $this->MODULE->Nom.": Exécution SQL impossible\n";
					return false;
				}
		
		if(is_bool($req)){
			$NoReponse=true;
		}
		if (!$NoReponse){
			$nb_total_ligne=$req->num_rows;                     // Nombre total de ligne	
		}
		else{
			if (isset($InsertTable)){
				$last_id = self::$db_link->insert_id ;
				$req=$last_id ;
				}
			}
		
		if ($this->ActiveDebug){
			if (!is_dir($logFolder)){
				mkdir($logFolder,0777,true) ;
			}
			if ($DEBUG){	
				$ignoreRequete=self::$RequetteToIgnoreInLOG;
				$ignoreRequete[]='ALTER TABLE';
				$CanLog=true;
				foreach($ignoreRequete as $ignore){
					$ignore = strtolower($ignore." ");
					$requette = trim($SQL);
					$requette = strtolower($requette);
					if ( substr($requette,0,strlen($ignore)) == $ignore){
						$CanLog=false;
					}
				}
				// 1 : on ouvre le fichier
				try {	
					if ($CanLog){
						//echo "Ouverture du fichier ".$Fichier." par ".exec('whoami');				
						$monfichier = fopen($Fichier, 'a');
						$Dat=date("Y-m-d");
						$Tim=date("H:i:s");
						$TxLog=str_replace("\n","",$SQL) ;
						$TxLog=str_replace("\r\n","",$TxLog) ;
						$TxLog=str_replace("\r","",$TxLog) ;
						$TxT=$Dat.";".$Tim.";".$TxLog."\r\n" ;	
						fputs($monfichier, $TxT);
						// 2 : on fera ici nos opérations sur le fichier...
						// 3 : quand on a fini de l'utiliser, on ferme le fichier
						fclose($monfichier);
					}
				}
				catch(Exception $e){
					echo "Erreur systeme de fichier sur ".$Fichier.". ".$e->getMessage() ;
				}				
			}
		}
		
		return $req ;
	}	 

	public function AddToJournal($login=null,$IdTechnicien=null,$Tache='',$Note=''){
		//$this->MODULE ;
		if (!isset($login)){
			$login="SYSTEME NABYSY" ;
			if (isset($this->User)){
				if ($this->User->Id>0){
					$login=$this->User->Login;
					$IdTechnicien=$this->User->Id ;
				}
			}
		}

		$Dat=date("Y-m-d");
		$Tim=date("H:i:s");
		$IpClient=$_SERVER['REMOTE_ADDR'];
		$PortClient=$_SERVER['REMOTE_PORT'];
		$IdJ=0 ;
		$OK=false;
		$NomETS=$this->MODULE->Nom ;
		$Note="[".$NomETS."] ".$Note ;
		$login=self::$db_link->real_escape_string($login);
		$Note=self::$db_link->real_escape_string($Note);
		$Tache=self::$db_link->real_escape_string($Tache);
		
		$ChampDate="DateEnreg" ;
		$ChampHeure="HeureEnreg" ;
		$ChampOperation = "OPERATION" ;
		$ChampPortClient = "PortClient";
		$MyDB=new xDB($this) ;
		if(!$MyDB->ChampsExiste("journal",$ChampDate,$this->MainDataBase)){
			if($MyDB->ChampsExiste("journal","Date",$this->MainDataBase)){
				$ChampDate="Date" ;
				$ChampHeure="Heure" ;
				$ChampOperation = "Tache" ;
			}
		}
		if(!$MyDB->ChampsExiste("journal","IdUtilisateur",$this->MainDataBase)){
			$MyDB->AlterTable("journal","IdUtilisateur","VARCHAR(255)","ADD","0",$this->MainDataBase) ;
		}
		if(!$MyDB->ChampsExiste("journal","IP",$this->MainDataBase)){
			$MyDB->AlterTable("journal","IP","VARCHAR(255)","ADD","0.0.0.0",$this->MainDataBase) ;
		}
		if(!$MyDB->ChampsExiste("journal",$ChampPortClient,$this->MainDataBase)){
			$MyDB->AlterTable("journal",$ChampPortClient,"INT(11)","ADD","0",$this->MainDataBase) ;
		}
		$sqljo="INSERT INTO ".$this->MasterDataBase.".journal (".$ChampDate.", ".$ChampHeure.", IdUtilisateur, IP, ".$ChampPortClient.", ".$ChampOperation.",Note) VALUES ('$Dat','$Tim','$login','$IpClient','$PortClient','$Tache','$Note')"; 
		$reqJ=$this->ReadWrite($sqljo,true,'journal',false) ;
		if (!$reqJ)
			{
				echo $this->MODULE->Nom."Erreur interne du journal système ...".$reqJ ;
			}
		else{
			//echo $this->MODULE->Nom."N°Enregistrement Journal système ...".$reqJ ;
			
		}
		
		return $reqJ;
	}
	
	
	public function ConvertToUTF8($text) { 
		
		$text = htmlentities($text, ENT_NOQUOTES, "UTF-8");
		$text = htmlspecialchars($text); 
		return $text; 
	}
	
	public function CleanString($Text){
		$Text = str_replace(' ', '-', $Text); // Replaces all spaces with hyphens.

		return preg_replace('/[^A-Za-z0-9\-]/', '', $Text); // Removes special chars.
	}
	
	/* Retourne l'Heure UTC */
	public Function HeureUTC(){
		$tz = new DateTimeZone('utc');
		$DateServeur= new DateTime(date('Y-m-d H:i:s'));
		$DateServeur->setTimezone($tz);
		
		$Heure=$DateServeur->format('H:i:s') ;
		return $Heure;		
	}

	/**
	 * Retourne la Date UTC
	 * @param bool $FormatFR 
	 * @return string 
	 */
	public Function DateUTC($FormatFR=false){
		$tz = new DateTimeZone('utc');
		$DateServeur= new DateTime(date('Y-m-d H:i:s'));
		$DateServeur->setTimezone($tz);
		
		$DateT=$DateServeur->format('Y-m-d') ;
		if ($FormatFR){
			$DateT=$DateServeur->format('d-m-Y') ;
		}
		return $DateT;		
	}
	
	public function ReadConfig($Id=0){
		if (isset($_REQUEST['Token']) && !$this->DesableTokenCheckingOnLoad){
			$Token=$_REQUEST['Token'] ;
			$Auth=new xAuth($this);
			$UserToken=$Auth->DecodeToken($Token) ;
			//var_dump($UserToken)."</br>" ;
			//var_dump($User) ;
			$Err=new xErreur();
			if (!isset($UserToken)){
				$Err->TxErreur="Utilisateur introuvable !" ;
				$Err->OK=0;
				echo json_encode($Err) ;
				http_response_code(419);            
				exit ;
			}
			if (get_class($UserToken)=='xErreur'){
				$Err->TxErreur="Votre session à expirée." ;
				$Err->OK=0;
				echo json_encode($Err) ;
				http_response_code(419);            
				exit ;
			}	
			$this->User=new xUser($this,$UserToken->user_id) ;
		}

		
	}

	public function GetSite(){
		$url=$_SERVER['REQUEST_URI'] ;
		$chem=parse_url($url);
		$path = explode("/",$chem['path']);
		$n=0 ;
		$site="" ;
		if (count($path)>0){
			$site=$path[1] ;
		}
		return $site ;
	}
	
	public function SaveToFile($Infos){
		// 1 : on ouvre le fichier
		$Fichier="log/DumpData".date('mY').".csv" ; ;
		$Fichier="log/DumpData".date('mY').".txt" ; ;
		$monfichier = fopen($Fichier, 'a+');
		$Dat=date("Y-m-d");
		$Tim=date("H:i:s");
		fclose($monfichier);
	}

	/**
	 * Transforme un résultat MySQLi en Tableau de Donnée
	 * @param mysqli_result $Reponse : Le résultat MySQL
	 * @return array : Le resultat sous forme de tableau d'association
	 */
	public static function EncodeReponseSQL($Reponse){
		$Liste=array();
		if ($Reponse){
			while ($row = $Reponse->fetch_assoc()) {
				//array_push($Liste,$row);
				$Liste[]=$row;
			}
		}
		return $Liste ;		
	}

	/**
	 * Transforme un résultat MySQLi en Tableau de donnée numerotée de 0 à i élément.
	 */
	public function SQLToArrayNumerable($Reponse){
		$i=0;
		$Retour=null;
		while($data =$Reponse->fetch_assoc()){
			$Retour[$i]=$data; 
			$i++;
		}
		return $Retour ;
	}

	public static function utf8ize( $mixed ) {
		if (is_array($mixed)) {
			foreach ($mixed as $key => $value) {
				$mixed[$key] = self::utf8ize($value);
			}
		} elseif (is_string($mixed)) {
			return mb_convert_encoding($mixed, "UTF-8", "UTF-8");
		}
		return $mixed;
	}
	public static function EscapedForJSON($value){
		$escapers = array("\\", "/", "\"", "\n", "\r", "\t", "\x08", "\x0c");
		$replacements = array("\\\\", "\\/", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");

		return self::utf8ize($value) ;
	}

	/**
	 * Function Convertion SQLToJSON: Utilisé pour Convertir un resultat MySQLi en objet serialisé JSon
	 * @param mysqli_result|object $RS
	 * @return string JSON string
	 */
	public static function SQLToJSON($RS){
		if (!isset($RS)){
			return "[]" ;
		}
		$Reponse=$RS ;
		if (!is_array($RS)) {
			$Reponse=self::EncodeReponseSQL($RS);
			return $Reponse ;
		}
		//var_dump($Reponse) ;
		$vReponse=[];
		$Rep=json_encode($RS) ;
		foreach ($Reponse as $key => $Ligne){
			//var_dump($Reponse) ;
			if (is_array($Reponse[$key])){
				$Tableau=$Reponse[$key] ;
				//var_dump($Tableau) ;
				$rw=[] ;
				foreach ($Tableau as $key => $Ligne){
					if (isset($Tableau[$key])){						
						//print_r($Tableau[$key]);
						$rw[$key]=$Tableau[$key] ;
						
					}
				}
				$vReponse[]=$rw ;
				$Rep=json_encode($vReponse) ;  
			}else{
				$rw=[] ;
				//$rw[$key]=$Ligne[$key] ;
				//$vReponse[]=$Ligne ;
				if (isset($Reponse[$key])){
					$vReponse[$key]=$Ligne ;
				}
				$Rep=json_encode($vReponse) ;
				
			}
			//print_r($Reponse[$key])	 ;
			
		} 
		//print_r($Rep) ;
		return $Rep ;
	}

	public function TableExiste($Table){
		$TxSQL="SHOW TABLES like '".$Table."'" ;
		$reponse=$this->ReadWrite($TxSQL,null,null,false);
		if (count($reponse->fetch_all())>=1){
			return true;
		}
		return false;
	}

	public function CreateTable($NomTable){
		$ChampID="ID";
		$TxSQL="CREATE TABLE `".$NomTable."` (
			ID INT(11) AUTO_INCREMENT PRIMARY KEY 
			)" ;
		$ok=$this->ReadWrite($TxSQL,true,null,false) ;
		return $ok ;
	}

	public function AlterTable($NomTable,$NomChamp,$TypeVar='VARCHAR(255)',$AddOrChange='ADD',$ValDefaut=''){
		$TxSQL="ALTER TABLE ".$NomTable." ".$AddOrChange." ".$NomChamp." ".$TypeVar." NOT NULL DEFAULT '".$ValDefaut."' " ;
		$ok=$this->ReadWrite($TxSQL,true,null,false) ;
		return true;
	}

	public static function FormatNB($nombre,$lang="fr", int $arrondie = 2){
		if ($lang=="fr" or $lang=="FR"){
			$nb = number_format($nombre, $arrondie, ',', ' ');
		}
		if ($lang=="us" or $lang=="US"){
			$nb = number_format($nombre, $arrondie, '.', ',');
		}
		return $nb ;

	}

	function Select($sql) {

		$resultv=$this->ReadWrite($sql,null,null,false);
		if (!$resultv) {
			printf("Erreur SQL !<br> %s <br> %s\n", $sql,self::$db_link->error);
		}
		
		$tab_resultat=null;
		$i=0;
		while($data =$resultv->fetch_assoc()){
			$tab_resultat[$i]=$data; 
			$i++;
		}	
		return $tab_resultat;
	}

	function update($sql){	
		$mysqli = self::$db_link;
		$result=$mysqli->query($sql) ;
		if (!$result) {
			printf("Erreur SQL !<br> %s <br> %s\n", $sql, $mysqli->error);
		}
		$i=$mysqli->insert_id;
		//* Monitoring des requetes dans NAbysyGS
		if (isset($nabysy)){
			$Tache="REQUETE SQL - NAbySyGS";
			$Note=$sql ;
			$User ='NAbySyGS"' ;
			if ($_SESSION['user']){
				$User=$_SESSION['user'] ;
			}
			$nabysy->MaBoutique->AddToJournal($Tache,$Note) ;
		}	
		 
		return $i;
	} 

	/**
	 * Class casting: Utilisé pour dupliquer un objet (Class)
	 *
	 * @param string|object $destination
	 * @param object $sourceObject
	 * @return object
	 */
	function Cast($destination, $sourceObject):object
	{
		if (is_string($destination)) {
			$destination = new $destination();
		}
		$sourceReflection = new ReflectionObject($sourceObject);
		$destinationReflection = new ReflectionObject($destination);
		$sourceProperties = $sourceReflection->getProperties();
		foreach ($sourceProperties as $sourceProperty) {
			$sourceProperty->setAccessible(true);
			$name = $sourceProperty->getName();
			$value = $sourceProperty->getValue($sourceObject);
			if (is_object($value) && get_class($value) === 'stdClass') {
				$value = $this->Cast($name, $value); // ou autre logique
			}
			// Traitement récursif pour tableaux
			if (is_array($value)) {
				$value = array_map(function ($item) use ($name) {
					if (is_object($item) && get_class($item) === 'stdClass') {
						$className = ucfirst(rtrim($name, 's')); // ex : "users" → "User"
						if (class_exists($className)) {
							return $this->Cast($className, $item);
						}
					}
					return $item;
				}, $value);
			}
			if ($destinationReflection->hasProperty($name)) {
				$propDest = $destinationReflection->getProperty($name);
				$propDest->setAccessible(true);
				$propDest->setValue($destination,$value);
			} else {
				$destination->$name = $value;
			}
		}
		return $destination;
	}

	/**
	 * Charge les modules de gestion dans NAbySyGS stockés dans le dossier gs
	 * @param array $ListeRepertoirGS : Liste des répertoires à charger. Si vide, on charge tous les modules de gestion par défaut de NAbySyGS.
	 */
	public static function LoadModuleGS($ListeRepertoirGS = []){
		$RepWork=self::CurrentFolder()."gs" ;
		$ListeR=$ListeRepertoirGS ;
		if(count($ListeR) == 0){
			$ListeR=[];
			$ListeR[]="boutique" ;
			$ListeR[]="stock" ;
			// $ListeR[]="client" ;
			// $ListeR[]="fournisseur" ;
			$ListeR[]="facture" ;
			//$ListeR[]="bl" ;
			$ListeR[]="comptabilite" ;
			$ListeR[]="panier" ;
			$ListeR[]="userapi" ;
		}
		
		//On déclare un AutoLoad pour chaque catégorie
		$LstObs=[] ;
		
		foreach ($ListeR as $categorie){
			//var_dump(__NAMESPACE__ . '\\Editor\\') ;
			$dos=$RepWork.'/'.$categorie ;
			if (!is_dir($dos)){
				$retour=mkdir($dos,0777,true);
				if(!$retour){
					$nabysy=self::getInstance();
					self::$Log->AddToLog("Impossible de créer le répertoire de module de gestion: ".$dos);
				}
			}
			$debg=1;
			$AutoLoad=new \NAbySy\AutoLoad\xAutoLoad(self::$Main,$categorie,$RepWork);
			$AutoLoad->Register($LstObs,$debg) ;
			self::$ListeModuleAutoLoader[]=$AutoLoad ;
		}
		//On ajoute les modules de l'application hôte
		$dossierMod = self::CurrentFolder(true)."gs".DIRECTORY_SEPARATOR ;
		if(!is_dir($dossierMod)){
			try {
				$retour = mkdir($dossierMod,0777,true);
				if(!$retour){
					$nabysy=self::getInstance();
					self::$Log->AddToLog("Impossible de créer le répertoire de module de gestion: ".$dossierMod);
				}
			} catch (\Throwable $th) {
				$nabysy=self::getInstance();
				if($nabysy->ActiveDebug){
					throw $th;
				}
			}
		}
		$liste=scandir($dossierMod) ;
		foreach ($liste as $dossier){
			if ($dossier!="." && $dossier!=".." && $dossier!="Thumbs.db"){
				$dos=$dossierMod.$dossier ;
				if (is_dir($dos)){
					$AutoLoad=new \NAbySy\AutoLoad\xAutoLoad(self::$Main,$dossier,$dossierMod);
					$AutoLoad->Register($LstObs,1) ;
					self::$ListeModuleAutoLoader[]=$AutoLoad ;
				}
			}
		}	
	}

	/**
	 * Ajoute un Module de Gestion dans NAbySyGS. Le dossier du module n'existe pas il sera crée dans le dossier gs.
	 * @param string $categorie 
	 * @return bool 
	 */
	public static function AddModuleGS(string $categorie, bool $IsHostAppModule=true):bool{
		$RepWork=self::CurrentFolder()."gs" ;
		if($IsHostAppModule){
			$RepWork=self::CurrentFolder(true)."gs" ;
		}	
		//On déclare un AutoLoad pour chaque catégorie
		$LstObs=[] ;
		//var_dump(__NAMESPACE__ . '\\Editor\\') ;
		$dos=$RepWork.'/'.$categorie ;
		if (!is_dir($dos)){
			mkdir($dos,0777,true);
		}
		$AutoLoad=new \NAbySy\AutoLoad\xAutoLoad(self::$Main,$categorie,$RepWork);
		$AutoLoad->Register($LstObs,1) ;
		self::$ListeModuleAutoLoader[]=$AutoLoad ;
		return true;
	}

	/**
	 * Charge les librairies partagées entre les modules
	 */
	public static function LoadModuleLib($DebugLevel=0){  
			$rep="lib" ;
            $rep=str_replace('\\', DIRECTORY_SEPARATOR, $rep) ;
            
            $ListeDossier=[] ;
			$NbModule=0;
            if ($DebugLevel>2){
                self::$Log->AddToLog('Repertoire '.$rep.' Existe ? ') ;
            }            
            if(self::IsDirectory($rep)){  
                if ($DebugLevel>2){
                    self::$Log->AddToLog( 'OUI') ;
                }
                if($iteration = opendir($rep)){  
                    
                    while(($dos = readdir($iteration)) !== false)  
                    {  
                        if($dos != "." && $dos != ".." && $dos != "Thumbs.db")  
                        {  
                            $pathfile=$rep.DIRECTORY_SEPARATOR.$dos ;
                            if ($DebugLevel>2){
                                self::$Log->AddToLog( 'Repertoire Module '.$pathfile.' ? ') ;
                            }
                            if (is_dir($pathfile)){
                                $NbModule ++;
                                if ($DebugLevel>2){
                                    self::$Log->AddToLog( 'Librairie trouvé: '.$dos) ;
                                }
                                //Repertoir nom de module
                                if ($DebugLevel>2){
                                    self::$Log->AddToLog( 'OUI') ;
                                }
                                $Mod=[];
                                $Mod[0]=$dos ;
                                $Mod[1]=$pathfile ;
                                $ListeDossier[]=$Mod ;                                
                            }else{
                                if ($DebugLevel>2){
                                    self::$Log->AddToLog( 'NON') ;
                                }
                            }
                        }
                    }
                    closedir($iteration);  
                }  
            }else{
                if ($DebugLevel>2){
                    self::$Log->AddToLog( 'NON') ;
                }
            }
            
			foreach ($ListeDossier as $Librairie){
				$FichierInterface=$Librairie[1].DIRECTORY_SEPARATOR.$Librairie[0].".i.php" ;
				if (file_exists($FichierInterface)){
					include_once $FichierInterface ;
				}else{
					self::$Log->AddToLog($FichierInterface." introuvable");
					$Tache="ERREUR CHARGEMENT DES LIBRAIRIES";
					$Note=$FichierInterface." introuvable";
					$TxSQL="select * from journal j where j.Note like '%".$Note."%' and j.DateEnreg = '".date('Y-m-d')."'";
					$Lst=self::$Main->ReadWrite($TxSQL);
					if ($Lst->num_rows==0){
						self::$Main->AddToJournal('SYSTEME',0,$Tache,$Note);
					}
				}
			}

            return $ListeDossier ;
    } 

	/**
	 * Charge les Modules Externes tel que Le module de gestion interagissant via des API dstantes.
	 */
	public static function LoadExternalModuleLib($DebugLevel=0){  
			$rep= self::CurrentFolder(true)."moduleExterne" ;
            $rep=str_replace('\\', DIRECTORY_SEPARATOR, $rep) ;
			if (!file_exists($rep)){
				$Tache="CHARGEMENT DES LIBRAIRIES";
				$Note="SETUP: Création du Dossier ".$rep;
				self::$Main->AddToJournal('SYSTEME','0', $Tache,$Note);
				mkdir($rep,0777,true);
			}
            
            $ListeDossier=[] ;
			$NbModule=0;
            if ($DebugLevel>1){
                self::$Log->AddToLog( 'Repertoire '.$rep.' ? ') ;
            }            
            if(self::IsDirectory($rep)){  
                if ($DebugLevel>1){
                    self::$Log->AddToLog( 'OUI') ;
                }
                if($iteration = opendir($rep)){  
                    
                    while(($dos = readdir($iteration)) !== false)  
                    {  
                        if($dos != "." && $dos != ".." && $dos != "Thumbs.db")  
                        {  
                            $pathfile=$rep.DIRECTORY_SEPARATOR.$dos ;
                            if ($DebugLevel>1){
                                self::$Log->AddToLog( 'Repertoire Module '.$pathfile.' ? ') ;
                            }
                            if (is_dir($pathfile)){
                                $NbModule ++;
                                if ($DebugLevel>1){
                                    self::$Log->AddToLog( 'Librairie trouvé: '.$dos.'') ;
                                }
                                //Repertoir nom de module
                                if ($DebugLevel>1){
                                    self::$Log->AddToLog( 'OUI') ;
                                }
                                $Mod=[];
                                $Mod[0]=$dos ;
                                $Mod[1]=$pathfile ;
                                $ListeDossier[]=$Mod ;                                
                            }else{
                                if ($DebugLevel>1){
                                    self::$Log->AddToLog( 'NON') ;
                                }
                            }
                        }
                    }
                    closedir($iteration);  
                }  
            }else{
                if ($DebugLevel>1){
                    self::$Log->AddToLog( 'NON') ;
                }
            }
            
			self::$ListeModuleExterne=[];
			foreach ($ListeDossier as $Librairie){
				$FichierInterface=$Librairie[1].DIRECTORY_SEPARATOR.$Librairie[0].".i.php" ;
				//var_dump($FichierInterface);
				if (file_exists($FichierInterface)){
					include_once $FichierInterface ;
					try {
						$pre= "NAbySy\Lib\ModuleExterne\\".$Librairie[0];
						$ModClass=new $pre(self::$Main) ;
						if ($ModClass instanceof IModuleExterne){
							//self::$Log->Write("Module externe ".$Librairie[0]." chargé.");
							self::$ListeModuleExterne[]=$Librairie;
							//var_dump("Module externe ".$Librairie[0]." chargé.");
						}else{
							self::$Log->Write("La librairie ".$Librairie[0]." n'est pas un module compatible avec NAbySyGS");
						}
					} catch (\Throwable $th) {
						//C'est une Librairie Non Module Externe, par exemple TechnoWeb
					}
					
										
				}else{
					//var_dump($FichierInterface." introuvable");
					$Tache="CHARGEMENT DES LIBRAIRIES";
					$Note="ERREUR: ".$FichierInterface." introuvable";
					$TxSQL="select * from journal j where j.Note like '%".$Note."%' and j.DateEnreg = '".date('Y-m-d')."'";
					$Lst=self::$Main->ReadWrite($TxSQL);
					if ($Lst->num_rows==0){
						self::$Main->AddToJournal('SYSTEME',0,$Tache,$Note);
					}
				}
			}
            return $ListeDossier ;
    }

	/**
	 * Retourne les informations de création de class d'un module externe à partir de son interface
	 * @param string $ModuleName : Nom du Module recherché
	 * @return Object : Une Instance du Module
	 * 
	 */
	public  function GetModuleExterne(string $ModuleName):?object{
		$ModClass=null;
		if (count(self::$ListeModuleExterne)==0){
			return $ModClass;
		}
		foreach (self::$ListeModuleExterne as $Mod){
			if (strtolower($ModuleName)==strtolower($Mod[0])){
				$NewClassModule= "\NAbySy\Lib\ModuleExterne\\".$Mod[0];
				$ModClass=new $NewClassModule($this) ;
				return $ModClass ;
			}
		}
		return $ModClass;
	}

	public static function AddToObserveurListe( xObservGen $ModObserveur):bool{
		if (!isset($ModObserveur)){
			return false ;
		}
		//echo "Origne de l'ajout: </br>";
		//var_dump(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,2));

		foreach (self::$ListeModuleObserv as $Obs){
			if (strtolower($ModObserveur->Nom)==strtolower($Obs->Nom)){
				//var_dump(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,2));
				return true;
			}
		}
		self::$ListeModuleObserv[]=$ModObserveur ;
		//echo "Observateur ".$ModObserveur->Nom." ajouté </br>";
		//var_dump(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2));
		return true ;
	}

	public static function RaiseEvent($ClassName,$Arguments=null){
		$EventType=null;
		$nArg=$Arguments ;
		$Param=[] ;
		$NbArg=1 ;
		if (!is_array($Arguments)){
			var_dump(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,2));
			var_dump($Arguments) ;
		}
		
		if (is_array($Arguments)){
			$NbArg=count($Arguments) ;
			if (count($Arguments)){
				$EventType=$Arguments[0];
				//var_dump($nArg[2]);
				for ($x=0;$x++;$x<count($Arguments)-1){
					$Param[$x]=$Arguments[$x];
				}
				//var_dump($nArg);
			}
		}else{
			$EventType=$Arguments ;
			$Param[0]=$EventType ;
		}
		$TEvent=explode('\\',$EventType);
		$nb=count($TEvent) ;
		if ($nb>0){
			$nb =$nb-1 ;
			$EventType=$TEvent[$nb];
			$Param[0]=$EventType ;
		}
		foreach (self::$ListeModuleObserv as $ModObserveur){
			
			foreach ($ModObserveur->ListeObservable as $Observable){
				//self::$Log->Write("Event Recherche: ".$ClassName.' : '.json_encode($Param) ) ;				
				if ($EventType==$Observable){	
					//self::$Log->Write("Event Exection: ".$ClassName.' : '.json_encode($Param).' : '.$nArg[$NbArg-1]) ;				
					$ModObserveur->RaiseEvent($ClassName,$Param,$nArg[$NbArg-1]) ;					
				}
			}
		}
	}

	/**
	 * Indique si un fichier est un dossier ou pas.
	 * La fonctionne is_dir de php ne traitre pas normalement le systeme de fichier windows
	 */
	public static function IsDirectory($DirName){
		return is_dir($DirName);
	}

	/* ---------------------------------------------------------------------------- */

	/**
	 *  An example CORS-compliant method.  It will allow any GET, POST, or OPTIONS requests from any
	 *  origin.
	 *
	 *  In a production environment, you probably want to be more restrictive, but this gives you
	 *  the general idea of what is involved.  For the nitty-gritty low-down, read:
	 *
	 *  - https://developer.mozilla.org/en/HTTP_access_control
	 *  - https://fetch.spec.whatwg.org/#http-cors-protocol
	 *
	 */
	function AutorisationCORS() {
		
		// Allow from any origin
		if (isset($_SERVER['HTTP_ORIGIN'])) {
			// Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
			// you want to allow, and if so:
			header("Access-Control-Allow-Origin: *");
			//header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Max-Age: 86400');    // cache for 1 day
		}else{
			header("Access-Control-Allow-Origin: *");
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Max-Age: 86400');    // cache for 1 day
		}
		
		// Access-Control headers are received during OPTIONS requests
		if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
			
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
				// may also be using PUT, PATCH, HEAD etc
				header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE");
			
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
				header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
		
			exit(0);
		}
		
		//echo "You have CORS!";
	}

	/**
	 *  An example CORS-compliant method.  It will allow any GET, POST, or OPTIONS requests from any
	 *  origin.
	 *
	 *  In a production environment, you probably want to be more restrictive, but this gives you
	 *  the general idea of what is involved.  For the nitty-gritty low-down, read:
	 *
	 *  - https://developer.mozilla.org/en/HTTP_access_control
	 *  - https://fetch.spec.whatwg.org/#http-cors-protocol
	 *
	 */
	public static function AllowCORS() {
		
		// Allow from any origin
		if (isset($_SERVER['HTTP_ORIGIN'])) {
			// Decide if the origin in $_SERVER['HTTP_ORIGIN'] is one
			// you want to allow, and if so:
			header("Access-Control-Allow-Origin: *");
			//header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Max-Age: 86400');    // cache for 1 day
		}else{
			header("Access-Control-Allow-Origin: *");
			header('Access-Control-Allow-Credentials: true');
			header('Access-Control-Max-Age: 86400');    // cache for 1 day
		}
		
		// Access-Control headers are received during OPTIONS requests
		if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
			
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
				// may also be using PUT, PATCH, HEAD etc
				header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
			
			if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
				header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
		
			exit(0);
		}
		
		//echo "You have CORS!";
	}

	/**
	 * Vérifie l'authentification de l'utilisateur en cour
	 * @param bool $SendReponse : Si vrai, envoie automatiquement une réponse au client quand la vérification retourne false.
	 * @return bool : Vrai si correctement authentifié
	 */
	public function ValideUser($SendReponse=true):bool{
		$Err=new xErreur;
		if(!self::$NO_AUTH){
			if (!isset($this->User)){
				//Definition d'un utilisateur par défaut en mode NO_AUTH
				$this->User=new xUser($this,null,self::GLOBAL_AUTO_CREATE_DBTABLE) ;
				$this->User->Login="NO_AUTH_USER" ;
				$this->User->Password="" ;
				$this->User->NiveauAcces=1 ;
			}
			return true ;
		}
		if (!isset($this->User) || $this->User->Id==0){
			self::$Log->Write("Laissez moi vérifier a nouveau l'utilisateur en cour ...");
			self::ReadHttpAuthRequest();
		}
		if (!isset($this->User)){
			self::ReadHttpAuthRequest();
			if ($SendReponse==true){
				$Err->TxErreur='Vous n\'etes pas authentifié !' ;
				$Err->Source= __FUNCTION__ ;
				$reponse=json_encode($Err) ;
				echo $reponse ;				
			}
			return false ;	
		}
		if ($this->User->Id==0){
			//echo "Variable Actuelle: ".$_REQUEST['Token']."</br>" ;
			self::ReadHttpAuthRequest();
			if ($SendReponse==true){
				$Err->TxErreur='Id User introuvable. Vous n\'etes pas authentifié !' ;
				$Err->Source= __FUNCTION__ ;
				$reponse=json_encode($Err) ;
				echo $reponse ;				
			}
			return false ;	
		}
		if ($this->User->Id==0){		
			if ($SendReponse==true){
				$traces = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,2);
				foreach ($traces as $trace) {
					//echo json_encode($trace);
					self::$Log->Write('Erreur Authentification: Fichier: '.$trace['file'].' Ligne: '.$trace['line'].' Fonction: '.$trace['function']) ;
					//echo '<br>Fichier: '.$trace['file'].' Ligne: '.$trace['line'].' Fonction: '.$trace['function'].'</br>' ;
				}
				//var_dump(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT,3));
				$Err->TxErreur='Vous n\'etes pas authentifié !' ;
				$Err->Source= __FUNCTION__ ;
				$reponse=json_encode($Err) ;
				echo $reponse ;				
			}
			return false ;
		}
		return true;
	}


	/**
	 * Retourne la dernière erreur liée à un appel aux fonctions JSON_ENCODE ou DECODE
	 */
	function GetJsonError(){
		$TxErreur=null;
		switch (json_last_error()) {
			case JSON_ERROR_NONE:
				$TxErreur=' - No errors';
			break;
			case JSON_ERROR_DEPTH:
				$TxErreur=' - Maximum stack depth exceeded';
			break;
			case JSON_ERROR_STATE_MISMATCH:
				$TxErreur=' - Underflow or the modes mismatch';
			break;
			case JSON_ERROR_CTRL_CHAR:
				$TxErreur=' - Unexpected control character found';
			break;
			case JSON_ERROR_SYNTAX:
				$TxErreur=' - Syntax error, malformed JSON';
			break;
			case JSON_ERROR_UTF8:
				$TxErreur=' - Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
			default:
				$TxErreur=' - Unknown error';
			break;
		}
		if (isset($TxErreur)){
			return $TxErreur ;
		}
		return null;
	}

	/**
	 * Permet l'ajout d'un module de réduction tel que les Bon d'Achat
	 * qui seront appelés a chaque opération de facturation.
	 * @param object $ModuleInstance : Instance du module
	 */
	public function RegisterBonAchatManager(IBonAchatManager $ModuleInstance):bool{
		//var_dump($ModuleInstance);
		if ($ModuleInstance instanceof IBonAchatManager){
			self::$ListeModuleBonAchat[]=$ModuleInstance ;
			return true;
		}
		return false;
	}

	public function PresenceBonAchatModule(string $HandleNomModule):bool {		
		foreach (self::$ListeModuleBonAchat as $ModBonAchat){
			if ($ModBonAchat instanceof IBonAchatManager){
				if (isset($ModBonAchat)){
					if ( strtolower($ModBonAchat->HandleModuleName()) == strtolower($HandleNomModule)){
						return true;
					}
				}
			}
		}
		//$BonAch=new xBonAchatManager($this);

		if ($HandleNomModule !==''){
			try{
				$ModCtl="NAbySy\Lib\BonAchat\\".$HandleNomModule;
				$ModB=new $ModCtl($this);
				if ($ModB instanceof IBonAchatManager){
					return true;
				}
			}catch(Exception $ex){

			}
		}
		return false;
	}

	/**
	 * Retourne le module de paiement correspondant au nom du Handle
	 * ou nul si le module est introuvable
	 */
	public function GetModulePaie(string $HandleNomModule):?IModulePaieManager{
		if (count(self::$ListeModulePaiement)>0){
			foreach(self::$ListeModulePaiement as $Mod){
				//var_dump(get_class($Mod));
				try{
					if ($Mod instanceof IModulePaieManager){
						if ($Mod->HandleModuleName() == $HandleNomModule){
							return $Mod;
							break;
						}
					}
				}
				catch (Exception $ex){

				}
			}
		}
		return null ;
	}

	/**
	 * Convertir si diponible les datas recus par la méthode Post dans le corp du document HTTP
	 * @param bool $MAJ_AUTO_REQUETTE | Si Vrai, met à jour le tableau $_REQUEST automatiquement
	 * @return null|array 
	 */
	public static function ConvertBodyPostToArray($MAJ_AUTO_REQUETTE=true):?array{
		$sortie=null;
		if(count($_GET)>0){
			//self::$Log->AddToLog("Des données GET ont été trouvées: ".json_encode($_GET));
			foreach ($_GET as $key => $value) {
				$CanAdd=true;
				foreach ($_REQUEST as $pkey => $pvalue) {
					if($pkey == $key){
						$CanAdd=false;
						break;
					}
				}
				if($CanAdd){
					$_REQUEST[$key]=$value;
				}
			}
		}
		if(count($_POST)>0){
			//self::$Log->AddToLog("Des données POST_FORM ont été trouvées: ".json_encode($_POST));
			foreach ($_POST as $key => $value) {
				$CanAdd=true;
				foreach ($_REQUEST as $pkey => $pvalue) {
					if($pkey == $key){
						$CanAdd=false;
						break;
					}
				}
				if($CanAdd){
					$_REQUEST[$key]=$value;
				}
			}
		}
		$LISTE_VARIABLE=$_REQUEST;

		$corps=file_get_contents('php://input');
		if (isset($corps)){
			if ($corps !==""){
				// self::$Log->Write("POST DATA Brute: ".$corps);
				// self::$Log->Write("<---------------------------------------------------->");
				$sortie = html_entity_decode($corps);
				// self::$Log->Write("POST DATA Après html_entity_decode: ".$sortie);
				// self::$Log->Write("<---------------------------------------------------->");
				/* $sortie = urldecode($sortie);
				self::$Log->Write("POST DATA Après urldecode: ".$sortie);
				self::$Log->Write("<---------------------------------------------------->"); */
				
			}
		}

		if(isset($sortie)){
			if (trim($sortie)!==""){
				$contenue = $sortie;
				$lignePar=explode("&",$contenue);
				if (is_array($lignePar)){
					$tableau=[];
					foreach($lignePar as $lignedata){
						$data=explode("=",$lignedata);
						if (is_array($data)){
							if ($data[0] !==""){
								$tableau[$data[0]] = $data[1];
							}
						}
					}
					if (count($tableau)){
						$sortie=$tableau ;
					}
				}
				$postData=$sortie;
				if($postData){
					if(count($postData)){
						//self::$Log->AddToLog("Des données POST ont été trouvées: ".json_encode($postData));
						if(count($_REQUEST)>0){
							foreach ($postData as $key => $value) {
								$CanAdd=true;
								foreach ($_REQUEST as $pkey => $pvalue) {
									if($pkey == $key){
										$CanAdd=false;
										break;
									}
								}
								if($CanAdd){
									$_REQUEST[$key]=$value;
								}
							}
						}else{
							$_REQUEST = $postData;
						}
					}
				}
			}
		}
		$LISTE_VARIABLE=$_REQUEST;
		if($LISTE_VARIABLE){
			if(count($LISTE_VARIABLE)){
				$actual_link = (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
				//self::$Log->AddToLog("Requette Recus ".$actual_link.": Variable de Requette = ".json_encode($LISTE_VARIABLE));
			}
		}		
		return $LISTE_VARIABLE;
	}

	public function MAJ_DB(){
		
			$MySQL=new xDB($this) ;
			$MySQL->DebugMode=false ;
			$Tabl="parametre";
			if (!$MySQL->TableExiste($Tabl)){
				//Création de la Table Entete
				$MySQL->CreateTable($Tabl);
				//Création des Champs
				$MySQL->AlterTable($Tabl,'MONNAIS','VARCHAR(255)','ADD','F CFA') ;
				$MySQL->AlterTable($Tabl,'MONNAIS2','VARCHAR(255)','ADD','FRANCS CFA') ;				
			}							
			return true ;	
				
	}		

	public function __debugInfo() {
		$NbRoute=0;
		if(isset(self::$UrlRouter)){
			$NbRoute=self::$UrlRouter->count();
		}
        return array(
			'Serveur' => $this->db_serveur,
			'DBUser' => $this->db_user,
			'DBPwd' => '******',
			'DB' =>  $this->DataBase,
			'MasterDB' => $this->MainDataBase,
			'Port' => $this->db_port,
            'InfoClient' => $this->MODULE,
			'URLRoute' => $NbRoute,
        );
    }

	/**
	 * Démarre l'application NAbySyGS et charge tous les modules de gestion.
	 * @param xStartUpInfo $StartInfo : Informations rélative au démarrage du programme
	 * @return ?xNAbySyGS : Instance de l'application NAbySyGS 
	 */
	public static function Start(xStartUpInfo $StartInfo):?xNAbySyGS{
		if (session_status() == PHP_SESSION_NONE) {
			session_start();
		}
		if (!isset($StartInfo)){
			throw new Exception("Error: NAbySyGS StartInfo is required.", ERR_STARTUP_INFO_MISSING);
			return null ;
		}
		if (!isset($StartInfo->InfoClientMCP)){
			throw new Exception("Error: NAbySyGS MCP Custumer information is required.", ERR_STARTUP_INFO_MISSING);
			return null ;
		}
		if (!isset($StartInfo->Connexion)){
			throw new Exception("Error: NAbySyGSServer information is required.", ERR_STARTUP_INFO_CONN_MISSING);
			return null ;
		}
		if (trim($StartInfo->Connexion->Serveur) =="" ){
			throw new Exception("Error: NAbySyGSServer address is required.", ERR_STARTUP_INFO_CONN_MISSING);
			return null ;
		}

		$Conn = $StartInfo->Connexion ;
		
		$nabysy = new xNAbySyGS($Conn->Serveur,$Conn->DBUser,$Conn->DBPwd,$StartInfo->InfoClientMCP,$Conn->DB,$Conn->MasterDB, $Conn->Port, self::$BASEDIR, $StartInfo->DesableTokenAuth)  ;
		if ($nabysy == false){
			$Err=new xErreur();
			$Err->OK=0;
			$Err->TxErreur = "Le module ".$StartInfo->InfoClientMCP->Nom." a rencontré une erreur.";
			echo json_encode($Err) ;
			return null ;
		}
		$nabysy->MODULE->Actif=true;
		$nabysy->ActiveDebug= boolval ($StartInfo->DebugMode) ;
		ini_set('display_errors', 0);
		ini_set('display_startup_errors', 0);
		error_reporting(E_ERROR);
		if($nabysy->ActiveDebug){
			ini_set('display_errors', $StartInfo->DisplayErrors);
			ini_set('display_startup_errors', $StartInfo->DisplayStartUpErrors);
			error_reporting($StartInfo->ErrorReporting);
		}
		$nabysy->ChargeInfos();
		$nabysy->AutorisationCORS();
		return $nabysy ;
	}

	/**
	 * Démarre une nouvelle instance de NAbySyGS avec les informations de connexion
	 * @param string $AppName | Nom de l'application utilisans NAbySyGS
	 * @param string $NomClient | Nom du client
	 * @param string $AdresseClient | Adresse du client
	 * @param string $TelClt | Téléphone du client
	 * @param string $Database | Nom de la base de données
	 * @param string $MasterDataBase | Nom de la base de données globale/centrale utilisée par NAbySyGS
	 * @param string $Server | Adresse du serveur de base de donnée. Par défaut: 127.0.0.1
	 * @param string $DBUser | Utilisateur de la base de donnée
	 * @param string $DBPwd | Mot de passe de la base de donnée
	 * @param int $DBPort | Numero du port de la base de donnée. Par défaut le port mysql/mariadb 3306
	 * @return xNAbySyGS 
	 * @throws Exception 
	 */
	public static function Init(string $AppName="NAbySyGS-PAM Internal Service API", string $NomClient="Paul & Aïcha Machinerie SARL",
		string $AdresseClient="Dakar Zack Mbao", string $TelClt="+221 33 836 14 77", string $Database="nabysygs", 
		string $MasterDataBase="nabysygs", string $Server="127.0.0.1", string $DBUser="root", string $DBPwd="", int $DBPort=3306,
		?string $baseDir = null, ?bool $DesableTokenAuth=true):xNAbySyGS{
		$InfoClientMCP = new ModuleMCP();
		$InfoClientMCP->Nom = $AppName ;
		$InfoClientMCP->MCP_CLIENT = $NomClient;
		$InfoClientMCP->MCP_ADRESSECLT=$AdresseClient ;
		$InfoClientMCP->MCP_TELCLT= $TelClt ;
		
		$Connexion=new xConnexionInfo();
		$Connexion->DB=$Database;
		$Connexion->MasterDB = $MasterDataBase;
		$Connexion->Serveur = $Server;
		$Connexion->DBUser= $DBUser;
		$Connexion->DBPwd= $DBPwd;
		$Connexion->Port= $DBPort;
		self::$BASEDIR = $baseDir ;

		$StartInfo = new xStartUpInfo($InfoClientMCP, $Connexion);
		$StartInfo->DesableTokenAuth = $DesableTokenAuth ;
		return self::Start($StartInfo);
		
	}

	public static function SetShowDebug(bool $ShowDebug=true, int $DebugLevel=1){
		if($ShowDebug){
			ini_set('display_errors', $DebugLevel);
			ini_set('display_startup_errors', $DebugLevel);
			error_reporting(E_ALL);
		}else{
			ini_set('display_errors', 0);
			ini_set('display_startup_errors', 0);
			error_reporting(E_ERROR);
		}
	}

	/**
	 * Indique au module d'authentification NAbySyGS Auth de retourner ou non la réponse d'authentification directement au client
	 * @param bool $SendReponse 
	 * @return void 
	 */
	public static function SetSendAuthReponse(bool $SendReponse=true){
		self::$SendAuthReponse=$SendReponse;
	}

	/**
	 * Définit la durée d'une session utilisateur
	 * @param int $DureeAuth 
	 * @return bool 
	 */
	public static function SetAuthSessionTime(int $DureeAuth = 10800):bool{
		if($DureeAuth<0){
			return false;
		}
		self::$AUTH_DUREE_SESSION = $DureeAuth ;
		return true;
	}

	/**
	 * Retourne la version de NAbySyGS
	 * @return string : Version de NAbySyGS
	 */
	public static function VERSION(){
		return NABYSY_VERSION ;
	}

	/**
	 * Retourne le dossier courant du module où est installé NAbySyGS avec le séparateur à la fin du repertoir
	 * @param bool $HostAppFolder : Si vrai retourne le dossier de travaille de l'application hôte
	 * @return string 
	 */
	public static function CurrentFolder(bool $HostAppFolder=false):string{
		if ($HostAppFolder){
			$Rep = $_SERVER['DOCUMENT_ROOT'] ;
			if(isset(self::$BASEDIR) && self::$BASEDIR !==''){
				$PrecRep = $Rep ;
				$Rep .= DIRECTORY_SEPARATOR.self::$BASEDIR ;
				if(!is_dir(str_replace('/',DIRECTORY_SEPARATOR,$Rep))){
					echo "Creation du dossier ".$Rep." dans ". $PrecRep ." !</br>";
					try {
						mkdir($Rep,0777,true);
					} catch (\Throwable $th) {
							throw $th;
					}
				}
				if(!is_dir(str_replace('/',DIRECTORY_SEPARATOR,$Rep))){
					throw new Exception("Basedir ".$Rep." introuvable !", 1);
				}
				if(self::$GSSubDirectory !== ""){
					$Rep .= DIRECTORY_SEPARATOR.self::$GSSubDirectory ;
					if(!is_dir(str_replace('/',DIRECTORY_SEPARATOR,$Rep))){
						echo "Creation du sous-dossier ".self::$GSSubDirectory." dans ". $PrecRep ." !</br>";
						try {
							mkdir($Rep,0777,true);
						} catch (\Throwable $th) {
								throw $th;
						}
					}
					if(!is_dir(str_replace('/',DIRECTORY_SEPARATOR,$Rep))){
						throw new Exception("GS Sub Directory ".self::$GSSubDirectory." introuvable dans ". $PrecRep ." !", 1);
					}
				}
			}
			$Rep=str_replace('/',DIRECTORY_SEPARATOR,$Rep)  ;
		}else{
			$Rep=dirname(__FILE__);
		}
		$Rep=str_replace('\\',DIRECTORY_SEPARATOR,$Rep) . DIRECTORY_SEPARATOR ;
		return $Rep ;
	}

	/**
	 * Retourne le dossier contenant les catégories des modules de NAbySyGS sans le séparateur de dossier à la fin
	 */
	public static function ModuleGSFolder():string{
		$rep=self::CurrentFolder().'gs' ;
		return $rep ;
	}

	/**
	 * Retourne le dossier contenant les catégories des modules de NAbySyGS personnalisés de l'application hôte sans le séparateur de dossier à la fin
	 */
	public static function ModuleGSHostFolder():string{
		$rep=self::CurrentFolder(true).'gs' ;
		return $rep ;
	}

	/**
	 * Retourne un objet Liste typée.
	 * @param mixed $Objet : Le typage accepté par la liste
	 * @return xNAbySyCustomListOf 
	 */
	public static function ListOf(...$constructorArgs):xNAbySyCustomListOf{
		$Objet = $constructorArgs[0];
		return xNAbySyCustomListOf::GetListOf($Objet, $constructorArgs);
	}

	/**
	 * Retourne l'Objet principal NAbySyGS
	 * @return xNAbySyGS 
	 */
	public static function getInstance(?xBoutique $Boutique=null, bool $IgnoreTechnoWeb=false):xNAbySyGS{
		$nab=self::$Main;
		if(!$IgnoreTechnoWeb && !isset($Boutique)){
			try {
				if(isset($nab::$TechnoWEBMgr)){
					//self::$Log->Write("Je suis ici ".__CLASS__);
					if($nab::$TechnoWEBMgr->Ready()){
						self::$Main->LoadDataBaseFromTechnoWEBClient(); ;
					}
				}
			} catch (\Throwable $th) {
				self::$Log->Write($th->getMessage());
			}
		}
		
		if(isset($Boutique)){
			if($Boutique->Id>0){
				$nab->SelectBoutique($Boutique->Id);
			}
		}
		return $nab;
	}

	/**
	 * Permet de personnaliser les champs de la base de donnée à partir d'un fichier de configuration JSON
	 */
	public  static function LoadConfigFile(string $FichierConfig = "",bool $forceArray=true, bool $createIfNotExist=true){
		if($FichierConfig==''){
			$FichierConfig = self::$Main->CurrentFolder(true) . "appconfig.json" ;
		}
        if (!file_exists($FichierConfig)){
			if (!$createIfNotExist){
				return false ;
			}
			$ParamDefaut['APP']=self::$Main->MODULE->Nom;
			$ParamDefaut['APP_VERSION']=self::$Main->MODULE->Version;
			if (!self::SaveConfigToFile($FichierConfig, $ParamDefaut)){
				return false;
			}
        }
        //On récupere la configuration
        $string = file_get_contents($FichierConfig);
		if ($string==""){
			$ParamDefaut['APP']=self::$Main->MODULE->Nom;
			$ParamDefaut['APP_VERSION']=self::$Main->MODULE->Version;
			if (!self::SaveConfigToFile($FichierConfig, $ParamDefaut)){
				return false;
			}
			$string = file_get_contents($FichierConfig);
		}
		try{
			$MyParametre = json_decode($string,$forceArray);
		}catch(Exception $ex){
			$TxMsg[] = "JSON ERROR: " . json_last_error();
			$TxMsg[] = $ex->getMessage();
			self::$Main->AddToJournal(__CLASS__.'.SaveConfigToFile', 0, "", "ERR: L".__LINE__.": ".json_encode($TxMsg));
			return false;
		}
        if (isset($MyParametre)){
            if (is_array($MyParametre) || is_object($MyParametre)){
                return $MyParametre;
            }
        }
		return false;
    }

	public static function SaveConfigToFile(string $FichierConfig='' ,array | object $Config=null):bool{
		if(trim($FichierConfig)==""){
			$FichierConfig = self::$Main->CurrentFolder(true). "appconfig.json" ;
		}
		$JsonFormat = "{}";
		if (trim($FichierConfig) ==''){
			return false;
		}
		try{
			if (is_object($Config)){
				$JsonFormat = json_encode($Config, JSON_FORCE_OBJECT);
			}
			if (is_array($Config)){
				$JsonFormat = json_encode($Config);
			}
		}catch(Exception $ex){
			$TxMsg[] = "JSON ERROR: " . json_last_error();
			$TxMsg[] = $ex->getMessage();
			self::$Main->AddToJournal(__CLASS__.'.SaveConfigToFile', 0, "", "ERR: L".__LINE__.": ".json_encode($TxMsg));
			return false;
		}
		try {
			// Extraire le chemin du répertoire à partir du chemin du fichier
			$repertoire = dirname($FichierConfig);
			// Vérifier si le répertoire existe, sinon le créer
			if (!is_dir($repertoire)) {
				if (!mkdir($repertoire, 0777, true)) {
					die("Erreur : Impossible de créer le répertoire $repertoire.\n");
				}
			}
		} catch (\Throwable $th) {
			self::$Main->AddToJournal("DEBUG",0,"LOAD ".__FUNCTION__,"Impossible de créer le dossier ". $repertoire);
		}
		try{
			//echo "Ecriture dans le fichier ". realpath($FichierConfig)."</br>" ;
			self::$Main->AddToJournal("DEBUG",0,"LOAD ".__FUNCTION__,"Ecriture dans ".realpath($FichierConfig));
			$file = fopen($FichierConfig,'w');
			if ($file){
				fwrite($file, $JsonFormat);
				fclose($file);
			}else{
				echo "Erreur d'écriture dans le fichier ". realpath($FichierConfig)."</br>" ;
				echo "ERREUR SYSTEME DE FICHIER: Impossible d'ecrire dans le fichier: ".realpath($FichierConfig)."</br>Debug Trace: ";
				echo json_encode(debug_backtrace());
				return false;
				//exit;
			}
			
		}catch(Exception $ex){
			$TxMsg[] = $ex->getMessage();
			self::$Main->AddToJournal(__CLASS__.'.SaveConfigToFile', 0, "", "ERR: L".__LINE__.": ".json_encode($TxMsg));
			return false;
		}
		return true;
		
	}

	public function LoadDataBaseFromTechnoWEBClient():xNotification{
		$BoutiqueCible=null;
		$Notif = new xNotification();
		$Notif->OK=0;
		if(isset($_REQUEST['IDTECHNOWEB'])){
			//Si IdTechnOWeb fournit, on recherche la bonne base de donnée.
			$IdTechnoWeb = trim($_REQUEST['IDTECHNOWEB']);
			
			if($IdTechnoWeb !==''){
				//self::$Log->AddToLog("Recherche de l'ID TechnoWEB ".$IdTechnoWeb);
				$CltTechnoWeb = self::$TechnoWEBMgr::GetClientTechnoWeb($IdTechnoWeb);
				if ($CltTechnoWeb){
					$CltTechnoWeb->Refresh();
					if($CltTechnoWeb->Id){
						//self::$Log->AddToLog("Client TechnoWEB Trouvé ".$CltTechnoWeb->RaisonSocial." ID=".$CltTechnoWeb->Id);
						$CltDataBase=trim($CltTechnoWeb->ServiceDB) ;
						if ($CltDataBase !==''){
							if($CltTechnoWeb->DBExiste($CltDataBase)){
								$BoutiqueCible = self::GetBoutique(-1,$CltTechnoWeb->RaisonSocial, $CltDataBase) ;
								if(!isset($BoutiqueCible)){
									self::$Log->Write(__FILE__." LIGNE: ".__LINE__ . ": Client ".$CltTechnoWeb->RaisonSocial." introuvable !");
									$IdB=null;
									self::$Log->Write(__FILE__." LIGNE: ".__LINE__. ": Création de la boutique pour: ". $CltTechnoWeb->RaisonSocial . " BD Existant: ".$CltDataBase." | MainDatabase: ".$this->MainDataBase) ;

									$Bout = new xORMHelper($this,$IdB,self::GLOBAL_AUTO_CREATE_DBTABLE,"boutique",$this->MainDataBase);
									$Bout->DBName = $CltDataBase ;
									$Bout->Nom = $CltTechnoWeb->RaisonSocial ;
									$Bout->DBase = $CltDataBase ;
									$Bout->Serveur = $this->MaBoutique->Serveur ;
									$Bout->DBUser = "pharmcp";
									$Bout->DBPassword = "microcp";
									
									if($Bout->Enregistrer()){
										$BoutiqueCible =  new xBoutique($this, $Bout->Id,false);
										$CanAdd=true;
										if(count($this->Boutiques)){
											foreach ($this->Boutiques as $BoutX) {
												if($Bout->Id == $BoutX->Id){
													$CanAdd=false;
													break;
												}
											}
										}
										if($CanAdd){
											$this->Boutiques[]=$BoutiqueCible ;
										}
										
										$CanAdd=true;
										if(count(self::$ListeBoutique)){
											foreach (self::$ListeBoutique as $BoutX) {
												if($Bout->Id == $BoutX->Id){
													$CanAdd=false;
													break;
												}
											}
										}
										if($CanAdd){
											self::$ListeBoutique[]=$BoutiqueCible ;
										}
									}

								}else{
									//self::$Main::$Log->AddToLog("DB Cible TechnoWEB : ".$BoutiqueCible->ToJSON());
									if($BoutiqueCible->DBName !== $CltDataBase){
										self::$Log->Write(__FILE__." LIGNE: ".__LINE__. ": Mise a jour de la boutique pour: ". $CltTechnoWeb->RaisonSocial . " BD Existant: ".$CltDataBase." | MainDatabase: ".$this->MainDataBase) ;
										$BoutiqueCible->DBName = $CltDataBase ;
										$BoutiqueCible->DBase = $CltDataBase ;
										$BoutiqueCible->Enregistrer() ;
									}
								}

								if($BoutiqueCible){
									//self::$Log->Write("CltDataBase: ".$CltDataBase." BD=".$BoutiqueCible->DataBase);
									//self::$Log->Write("Boutique précédente: ".$this->MaBoutique->Nom." BD=".$this->MaBoutique->DBName);
									//self::$Log->Write( "Future DataBase: ". $BoutiqueCible->DBName." dafinit dans ".$BoutiqueCible->FullTableName()) ;
									if($BoutiqueCible->DBname ==''){
										//var_dump(__FILE__." LIGNE: ".__LINE__. ": BoutiqueCible->DBname ='' !!!");
										self::$Log->Write(__FILE__." LIGNE: ".__LINE__. ": BoutiqueCible->DBname ='' !!!");
										$BoutiqueCible->DBname = $CltDataBase ;
									}

									$BoutiqueCible->DataBase = $BoutiqueCible->DBName ;
									$BoutiqueCible->DBName = $BoutiqueCible->DataBase ;
									$this->MaBoutique = $BoutiqueCible;

									$this->SelectDB($BoutiqueCible->DBName);
									$this->DataBase = $BoutiqueCible->DBName;
									$this->RepWork = $CltDataBase;
									$this->Parametre = new xORMHelper($this,1,true,$this->Parametre->Table, $CltDataBase);
									//self::$Log->AddToLog("La boutique en cour est maintenant: ".$this->MaBoutique->Nom." BD=".$this->MaBoutique->DataBase);
									if(!is_dir($this->RepWork)){
										try {
											//echo "Création du dossier ".$this->RepWork;
											mkdir($this->RepWork,0777,true);
										} catch (\Throwable $th) {
											self::$Log->Write("Impossible création du dossier ".$this->RepWork . " [ERR] ".$th->getMessage());
											$this->AddToJournal(null,null,"ERREUR",__FILE__. " L".__LINE__." : Erreur: ".$th->getMessage());
											//throw $th;
										}
									//}
									}
									$Dt=date('mY') ;
									self::$Log=new xLog($this,"NAbySyGS_Log-".$Dt.".csv") ;
									//echo __FILE__." Ligne ".__LINE__." Après DataBase= ". $BoutiqueCible->DataBase."</br>" ;
								}
							}else{
								$Err=new xErreur();
								$Err->TxErreur="Base de donnée ".$CltTechnoWeb->ServiceDB." introuvable sur le serveur !";
								$Err->SendAsJSON();
								return $Err ;
							}
							
						}
					}
				}else{
					$this->AutorisationCORS();
					$Err=new xErreur();
					$Err->TxErreur="ID-TechnoWeb introuvable !";
					$Err->SendAsJSON();
					return $Err ;
				}
			}
		}
		if($BoutiqueCible){
			//self::$Log->Write("IDTechnoWeb: ".$IdTechnoWeb,1) ;
			$this->RefreshParametre($BoutiqueCible->DataBase);
			$Notif->Contenue=$BoutiqueCible ;
			$Notif->OK=1 ;
		}else{
			$Notif->OK = 0 ;
			$Notif->TxErreur='Impossible de determiner votre compte TechnoWEB.';
		}
		return $Notif ;
	}

	public static function GetBoutique(?int $IdBout=-1, string $RaisonSocial="", string $CltDataBase=""):xBoutique|null{
		foreach (self::$ListeBoutique as $Bout) {
			if($IdBout == $Bout->Id){
				return $Bout ;
			}
			if(trim($CltDataBase) !==""){
				if(strtolower($Bout->DBName) ==strtolower($CltDataBase) ){
					return $Bout ;
				}
			}
			if(trim($RaisonSocial) !==""){
				if(strtolower($Bout->Nom) ==strtolower($RaisonSocial) ){
					return $Bout ;
				}
			}
		}
		//Si on est ici on va rechercher directement dans la BD
		self::$Log->Write(__FILE__." LIGNE: ".__LINE__ . ": Si on est ici on va rechercher directement dans la BD" );
		$BoutDefaut = self::getInstance()->MaBoutique;
		if ($BoutDefaut){
			$Lst = $BoutDefaut->ChargeListe(" (Nom like '".$RaisonSocial."' OR DBName like '".$CltDataBase."' ) ",null,"ID");
			if($Lst->num_rows){
				$rw=$Lst->fetch_assoc();
				$Bout = new xBoutique($BoutDefaut->MODULE, $rw['ID'],false);
				self::$ListeBoutique[]=$Bout ;
				return $Bout ;
			}else{
				self::$Log->Write(__FILE__." LIGNE: ".__LINE__ . ": Client ".$RaisonSocial." est aussi introuvable dans la boutique principale ".$BoutDefaut->DataBase." !");
			}
		}
		return null;
	}

	/**
	 * Rafraichit les paramètres ou recharge de nouveaux paramètres basés sur une base donnée cliente.
	 * @param string $CltDataBase : Base de donnée.
	 * @return bool 
	 */
	public function RefreshParametre(string $CltDataBase =''):bool{
		if(trim($CltDataBase) !==''){
			$this->Parametre=new xORMHelper($this,1,true,"parametre",$CltDataBase);
			//self::$Log->Write("RAFRAICHISSEMENT DES PARAMETRE: ".$this->Parametre->FullTableName());
		}

		if ($this->Parametre->Id==0){
			$Lst=$this->Parametre->ChargeListe();
			if ($Lst->num_rows){
				$rw=$Lst->fetch_assoc();
				$this->Parametre=new xORMHelper($this,$rw['ID'],true,"parametre",$CltDataBase);
			}else{
				//Il n'y a aucun parametre on le crée
				$this->Parametre->Monnaie = "F CFA";
				$this->Parametre->MonnaieLong = "FRANCS CFA";
				$Date=date('Y-10-01');
				$Dte=new DateTime($Date);
				$this->Parametre->DatePremiereScolarite = $Dte->format('Y-m-d');
				$this->Parametre->Enregistrer();
			}

		}
		if ($this->Parametre->DatePremiereScolarite == ''){
			$Date=date('Y-10-01');
			$Dte=new DateTime($Date);
			$this->Parametre->DatePremiereScolarite = $Dte->format('Y-m-d');
			$this->Parametre->Enregistrer();
		}
		return $this->Parametre->Refresh();
	}


	/**
	 * Retourne tous les paramètres passée depuis un appel d'url
	 * @return array 
	 */
	public static function GetHttpRequestParam():array{
		if(isset($GLOBALS['PARAM'])){
			return $GLOBALS['PARAM'] ;
		}
		return [];
	}

	/**
	 * Traite les demandes d'authentifications
	 * @return void 
	 */
	public static function ReadHttpAuthRequest(){
		
		$User=null ;
		//echo __FILE__." L".__LINE__.": Je lance ma lecture ici</br>";
		require 'auth.php';
		//var_dump(self::$Main->User->Login);exit;
		return;
	}

	/**
	 * Lance le traitement des requêttes HTTP par NAbySyGS
	 * @return void 
	 */
	public static function ReadHttpRequest(){
		if(!isset(self::$Main->User)){
			self::$Main->ChargeInfos();
		}
		require 'gs_api.php';
	}

	/**
	 * Permet de passer les routeurs basée sur l'url
	 * @return void 
	 */
	public static function ReadUrlRouterRequest():?xGSUrlRouterResponse{
		if(self::$UrlRouter->count()==0){return null;}
		return self::$UrlRouter::resolveUrlRoute();
	}
}

?>