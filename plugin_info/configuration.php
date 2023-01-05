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

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <div class="form-group">
      <label class="col-sm-2 control-label">{{Client ID}}</label>
      <div class="col-sm-3">
        <input type="text" class="configKey form-control" data-l1key="client_id" placeholder="{{Client ID}}"/>
      </div>
    </div>
    <div class="form-group">
      <label class="col-sm-2 control-label">{{Client secret}}</label>
      <div class="col-sm-3">
        <input type="text" class="configKey form-control" data-l1key="client_secret" placeholder="{{Client Secret}}"/>
      </div>
    </div>
    <div class="form-group">
      <label class="col-sm-2 control-label">{{Nom d'utilisateur}}</label>
      <div class="col-sm-3">
        <input type="text" class="configKey form-control" data-l1key="username" placeholder="{{Nom d'utilisateur}}"/>
      </div>
    </div>
    <div class="form-group">
      <label class="col-sm-2 control-label">{{Mot de passe}}</label>
      <div class="col-sm-3">
        <input type="password" class="configKey form-control" data-l1key="password" placeholder="{{Mot de passe}}"/>
      </div>
    </div>
    <div class="form-group">
      <label class="col-lg-2 control-label">{{Synchronisation}}</label>
      <div class="col-lg-4">
        <a class="btn btn-default" id="bt_syncWithNetatmo"><i class="fas fa-sync"></i> {{Synchroniser mes équipements}}</a>
      </div>
    </div>
  </fieldset>
</form>

<script>
$('#bt_syncWithNetatmo').on('click', function () {
  $.ajax({
    type: "POST",
    url: "plugins/mynetatmo/core/ajax/mynetatmo.ajax.php",
    data: {
      action: "sync",
    },
    dataType: 'json',
    error: function (request, status, error) {
      handleAjaxError(request, status, error);
    },
    success: function (data) {
      if (data.state != 'ok') {
        $('#div_alert').showAlert({message: data.result, level: 'danger'});
        return;
      }
      $('#div_alert').showAlert({message: '{{Synchronisation réussie}}', level: 'success'});
    }
  });
});
</script>
