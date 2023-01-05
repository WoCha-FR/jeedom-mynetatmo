<?php

/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';
if (!class_exists('mynetatmo_api')) {
  require_once __DIR__ . '/mynetatmo_api.class.php';
}
if (!class_exists('mynetatmo_weather')) {
  require_once __DIR__ . '/mynetatmo_weather.class.php';
}
if (!class_exists('mynetatmo_aircare')) {
  require_once __DIR__ . '/mynetatmo_aircare.class.php';
}
if (!class_exists('netatmo_energy')) {
  require_once __DIR__ . '/mynetatmo_energy.class.php';
}

class mynetatmo extends eqLogic {
  /*     * *************************Attributs****************************** */
  private static $_client = null;

  /*     * ***********************Methode static*************************** */
  
  public static function getClient() {
    if (self::$_client == null) {
      log::add(__CLASS__, 'debug', __FUNCTION__);
      self::$_client = new mynetatmo_api(array(
        'client_id' => config::byKey('client_id', __CLASS__),
        'client_secret' => config::byKey('client_secret', __CLASS__),
        'username' => config::byKey('username', __CLASS__),
        'password' => config::byKey('password', __CLASS__),
        'scope' => 'read_station read_homecoach read_thermostat write_thermostat',
        'access_token' => config::byKey('access_token', __CLASS__, null),
        'refresh_token' => config::byKey('refresh_token', __CLASS__, null)
      ));
    }
    return self::$_client;
  }

  public static function cron15(){
    try {
      mynetatmo_weather::refresh();
    } catch (\Exception $e) {
      log::add(__CLASS__,'debug','Weather : '.$e->getMessage());
    }
    try {
      mynetatmo_aircare::refresh();
    } catch (\Exception $e) {
      log::add(__CLASS__,'debug','Aircare : '.$e->getMessage());
    }
    try {
      mynetatmo_energy::refresh();
    } catch (\Exception $e) {
      log::add(__CLASS__,'debug','Energy : '.$e->getMessage());
    }
  }

  public static function request($_path, $_data = null, $_type = 'GET'){
    return self::getClient()->requestApi(trim($_path,'/'), $_data, $_type);
  }

  public static function sync(){
    mynetatmo_weather::sync();
    mynetatmo_energy::sync();
    mynetatmo_aircare::sync();
  }

  public static function devicesParameters($_device = '') {
    $return = array();
    $files = ls(__DIR__.'/../config/devices', '*.json', false, array('files', 'quiet'));
    foreach ($files as $file) {
      try {
        $return[str_replace('.json','',$file)] = is_json(file_get_contents(__DIR__.'/../config/devices/'. $file),false);
      } catch (Exception $e) {
        
      }
    }
    if (isset($_device) && $_device != '') {
      if (isset($return[$_device])) {
        return $return[$_device];
      }
      return array();
    }
    return $return;
  }

  /*     * *********************Méthodes d'instance************************* */

  public function postSave() {
    if ($this->getConfiguration('applyDevice') != $this->getConfiguration('device')) {
      $this->applyModuleConfiguration();
    }
    $cmd = $this->getCmd(null, 'refresh');
    if (!is_object($cmd)) {
      $cmd = new mynetatmoCmd();
      $cmd->setName(__('Rafraichir', __FILE__));
    }
    $cmd->setEqLogic_id($this->getId());
    $cmd->setLogicalId('refresh');
    $cmd->setType('action');
    $cmd->setSubType('other');
    $cmd->save();
  }

  public function applyModuleConfiguration() {
    $this->setConfiguration('applyDevice', $this->getConfiguration('device'));
    $this->save();
    if ($this->getConfiguration('device') == '') {
      return true;
    }
    $device = self::devicesParameters($this->getConfiguration('device'));
    if (!is_array($device)) {
      return true;
    }
    $this->import($device,true);
  }

  public function getImage() {
    if(file_exists(__DIR__.'/../config/devices/'.  $this->getConfiguration('device').'.png')){
      return 'plugins/mynetatmo/core/config/devices/'.  $this->getConfiguration('device').'.png';
    }
    return false;
  }

  /*     * **********************Getteur Setteur*************************** */
}

class mynetatmoCmd extends cmd {
  /*     * *************************Attributs****************************** */
  
  public function formatValueWidget($_value) {
    return $_value;
  }
  
  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    if ($this->getLogicalId() == 'refresh') {
      if($eqLogic->getConfiguration('type') == 'weather'){
        mynetatmo_weather::refresh();
      }
      if($eqLogic->getConfiguration('type') == 'aircare'){
        mynetatmo_aircare::refresh();
      }
      if($eqLogic->getConfiguration('type') == 'energy'){
        mynetatmo_energy::refresh();
      }
      return;
    }
    if($eqLogic->getConfiguration('type') == 'energy'){
      mynetatmo_energy::execCmd($this,$_options);
    }
  }
}
