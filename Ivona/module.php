<?
class Ivona extends IPSModule
{
    
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        
        //These lines are parsed on Symcon Startup or Instance creation
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyString("accessKey", "");
        $this->RegisterPropertyString("secretKey", "");
        $this->RegisterPropertyString("language", "");
        $this->RegisterPropertyString("voice", "");
        $this->RegisterPropertyString("rate", "");
        $this->RegisterPropertyString("volume", "");
        $this->RegisterPropertyString("defaultPath", "");
        $this->RegisterPropertyBoolean("deleteFiles", true);
        $this->RegisterPropertyInteger("deleteMinutes", 15);

    }
    
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        
        IPS_SetHidden($this->InstanceID,true);

        // End Register variables and Actions
        
        $deleteFilesScript = '<?
$path = IPS_GetProperty(IPS_GetParent($_IPS["SELF"]), "defaultPath");

if($path && IPS_GetProperty(IPS_GetParent($_IPS["SELF"]), "deleteFiles")){  

  $minutes = IPS_GetProperty(IPS_GetParent($_IPS["SELF"]), "deleteMinutes");
  if ($handle = opendir($path)) {

    while (false !== ($file = readdir($handle))) {
      if ((time()-filectime($path.'/'.$file)) < $minutes*60) {  // 86400 = 60*60*24
        if (preg_match('/\.mp3$/i', $file)) {
          unlink($path.'/'.$file);
        }
      }
    }
  }
}
?>';

        $deleteScriptID = @$this->GetIDForIdent("_deleteFiles");
        if ( $deleteScriptID === false ){
          $deleteScriptID = $this->RegisterScript("_deleteFiles", "_deleteFiles", $deleteFilesScript, 99);
        }else{
          IPS_SetScriptContent($deleteScriptID, $deleteFilesScript);
        }

        IPS_SetHidden($deleteScriptID,true);
        IPS_SetScriptTimer($deleteScriptID, 300); 


        // End add scripts for regular status and grouping updates
    }
    
    public function getMP3($text)
    {
        include_once(__DIR__ . "/ivona.php");
        (new new IVONA_TTS( $this->ReadPropertyString("accessKey") ,
                            $this->ReadPropertyString("secretKey") ,
                            $this->ReadPropertyString("language") ,
                            $this->ReadPropertyString("voice") ,
                            $this->ReadPropertyString("rate") ,
                            $this->ReadPropertyString("volume")))->get_mp3($text);
        
    }

    public function saveMP3($text,$file,$path='NONE')
    {

        if($path === 'NONE'){
          $path = $this->ReadPropertyString("defaultPath");
        }

        if($path === ''){
         $path = '/tmp';
        }

        $file = $path . "/" . $file;

        include_once(__DIR__ . "/ivona.php");
        (new new IVONA_TTS( $this->ReadPropertyString("accessKey") ,
                            $this->ReadPropertyString("secretKey") ,
                            $this->ReadPropertyString("language") ,
                            $this->ReadPropertyString("voice") ,
                            $this->ReadPropertyString("rate") ,
                            $this->ReadPropertyString("volume")))->save_mp3($text, $file);
        
    }
}
?>
