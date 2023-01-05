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

class mynetatmo_aircare {
  /*     * *************************Attributs****************************** */
  
  /*     * ***********************Methode static*************************** */
  
  public static function sync(){
    $aircare = mynetatmo::request('/gethomecoachsdata');
    log::add('mynetatmo','debug','[netatmo aircare] '.json_encode($aircare));
    if(isset($aircare['devices']) && count($aircare['devices']) > 0){
      foreach ($aircare['devices'] as &$device) {
        $eqLogic = eqLogic::byLogicalId($device['_id'], 'mynetatmo');
        if (isset($device['read_only']) && $device['read_only'] === true) {
          continue;
        }
        if(!isset($device['station_name']) || $device['station_name'] == ''){
          $device['station_name'] = $device['_id'];
        }
        if (!is_object($eqLogic)) {
          $eqLogic = new mynetatmo();
          $eqLogic->setIsVisible(1);
          $eqLogic->setIsEnable(1);
          $eqLogic->setName($device['station_name']);
          $eqLogic->setCategory('heating', 1);
        }
        $eqLogic->setConfiguration('type','aircare');
        $eqLogic->setEqType_name('mynetatmo');
        $eqLogic->setLogicalId($device['_id']);
        $eqLogic->setConfiguration('device', $device['type']);
        $eqLogic->save();
      }
      self::refresh($aircare);
    }
    
  }
  
  public static function refresh($_aircare = null) {
    $aircare = ($_aircare == null) ? mynetatmo::request('/gethomecoachsdata') : $_aircare;
    if(isset($aircare['devices']) && count($aircare['devices']) > 0){
      foreach ($aircare['devices'] as $device) {
        $eqLogic = eqLogic::byLogicalId($device["_id"], 'mynetatmo');
        if (!is_object($eqLogic)) {
          continue;
        }
        $eqLogic->setConfiguration('firmware', $device['firmware']);
        $eqLogic->setConfiguration('wifi_status', $device['wifi_status']);
        $eqLogic->setStatus('warning', !$device['reachable']);
        $eqLogic->save(true);
        if(isset($device['dashboard_data']) && count($device['dashboard_data']) > 0){
          foreach ($device['dashboard_data'] as $key => $value) {
            if ($key == 'max_temp') {
              $collectDate = date('Y-m-d H:i:s', $device['dashboard_data']['date_max_temp']);
            } else if ($key == 'min_temp') {
              $collectDate = date('Y-m-d H:i:s', $device['dashboard_data']['date_min_temp']);
            } else {
              $collectDate = date('Y-m-d H:i:s', $device['dashboard_data']['time_utc']);
            }
            $eqLogic->checkAndUpdateCmd(strtolower($key),$value,$collectDate);
          }
        }
      }
    }
  }
}
