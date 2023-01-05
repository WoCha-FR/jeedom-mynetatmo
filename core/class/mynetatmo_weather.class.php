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

class mynetatmo_weather {
  /*     * ***********************Methode static*************************** */
  public static function sync(){
    $weather = mynetatmo::request('/getstationsdata');
    log::add('mynetatmo','debug','[netatmo weather] '.json_encode($weather));
    if(isset($weather['devices']) &&  count($weather['devices']) > 0){
      foreach ($weather['devices'] as &$device) {
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
        $eqLogic->setConfiguration('type','weather');
        $eqLogic->setEqType_name('mynetatmo');
        $eqLogic->setLogicalId($device['_id']);
        $eqLogic->setConfiguration('device', $device['type']);
        $eqLogic->save();
        if(isset($device['modules']) &&  count($device['modules']) > 0){
          foreach ($device['modules'] as &$module) {
            $eqLogic = eqLogic::byLogicalId($module['_id'], 'netatmo');
            if(!isset($module['module_name']) || $module['module_name'] == ''){
              $module['module_name'] = $module['_id'];
            }
            if (!is_object($eqLogic)) {
              $eqLogic = new mynetatmo();
              $eqLogic->setName($module['module_name']);
              $eqLogic->setIsEnable(1);
              $eqLogic->setCategory('heating', 1);
              $eqLogic->setIsVisible(1);
            }
            $eqLogic->setConfiguration('type','weather');
            $eqLogic->setEqType_name('mynetatmo');
            $eqLogic->setLogicalId($module['_id']);
            $eqLogic->setConfiguration('device', $module['type']);
            $eqLogic->save();
          }
        }
      }
      self::refresh($weather);
    }
    
  }

  public static function refresh($_weather = null) {
    $weather = ($_weather == null) ? mynetatmo::request('/getstationsdata') : $_weather;
    if(isset($weather['devices']) &&  count($weather['devices']) > 0){
      foreach ($weather['devices'] as $device) {
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
            } else if ($key == 'max_wind_str') {
              $collectDate = date('Y-m-d H:i:s', $device['dashboard_data']['date_max_wind_str']);
            } else {
              $collectDate = date('Y-m-d H:i:s', $device['dashboard_data']['time_utc']);
            }
            $eqLogic->checkAndUpdateCmd(strtolower($key),$value,$collectDate);
          }
        }
        if(isset($device['modules']) && count($device['modules']) > 0){
          foreach ($device['modules'] as $module) {
            $eqLogic = eqLogic::byLogicalId($module["_id"], 'mynetatmo');
            if(!is_object($eqLogic)){
              continue;
            }
            $eqLogic->setConfiguration('rf_status', $module['rf_status']);
            $eqLogic->setConfiguration('firmware', $module['firmware']);
            $eqLogic->setStatus('warning', !$device['reachable']);
            $eqLogic->save(true);
            $devices = mynetatmo::devicesParameters($eqLogic->getConfiguration('device'));
            if(isset($devices['bat_min']) && isset($devices['bat_max'])){
              $eqLogic->batteryStatus(round(($module['battery_vp'] - $devices['bat_min']) / ($devices['bat_max'] - $devices['bat_min']) * 100, 0));
            }
            if(isset($module['dashboard_data'])){
              foreach ($module['dashboard_data'] as $key => $value) {
                if ($key == 'max_temp') {
                  $collectDate = date('Y-m-d H:i:s', $module['dashboard_data']['date_max_temp']);
                } else if ($key == 'min_temp') {
                  $collectDate = date('Y-m-d H:i:s', $module['dashboard_data']['date_min_temp']);
                } else if ($key == 'max_wind_str') {
                  $collectDate = date('Y-m-d H:i:s', $module['dashboard_data']['date_max_wind_str']);
                } else {
                  $collectDate = date('Y-m-d H:i:s', $module['dashboard_data']['time_utc']);
                }
                $eqLogic->checkAndUpdateCmd(strtolower($key),$value,$collectDate);
              }
            }
          }
        }
      }
    }
  }
}
