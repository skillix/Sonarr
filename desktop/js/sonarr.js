
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


/* Permet la réorganisation des commandes dans l'équipement */
$("#table_cmdFuture").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#table_cmdDownloaded").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#table_cmdMissing").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#table_cmdNotifications").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#table_cmdSearch").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#table_cmdFolder").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#table_cmdTags").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#table_cmdProfile").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });
$("#table_cmdOther").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });

$("#table_cmdOrder").sortable({ axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true });

/* Fonction permet²tant l'affichage des commandes dans l'équipement */
function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = { configuration: {} };
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {};
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
  tr += '<td style="width:60px;">';
  tr += '<span class="cmdAttr" data-l1key="id"></span>';
  tr += '</td>';
  tr += '<td style="min-width:300px;width:500px;">';
  tr += '<div class="row">';
  tr += '<div class="col-xs-7">';
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la commande}}">';
  tr += '</div>';
  trOther = tr;
  tr += '<div class="col-xs-5">';
  tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fas fa-flag"></i> {{Icône}}</a>';
  tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
  tr += '</div>';
  tr += '</div>';
  tr += '</td>';
  trType = '<td>';
  trType += '<label>' + init(_cmd.type) + '</label>';
  trType += '</td>';
  tr += trType;
  trOther += trType;
  tr += '<td style="min-width:80px;width:350px;">';
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/>{{Afficher}}</label>';
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized" checked/>{{Historiser}}</label>';
  tr += '</td>';
  tr += '<td style="min-width:80px;width:200px;">';
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> Tester</a>';
  }
  tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
  tr += '</tr>';
  trOther += '</tr>';
  var table = '';
  if (_cmd.logicalId == 'day_episodes' || _cmd.logicalId == 'day_episodes_raw') {
    table = '#table_cmdFuture';
  }
  if (_cmd.logicalId == 'day_ddl_episodes' || _cmd.logicalId == 'day_ddl_episodes_raw' || _cmd.logicalId == 'last_episode') {
    table = '#table_cmdDownloaded';
  }
  if (_cmd.logicalId == 'day_missing_episodes' || _cmd.logicalId == 'day_missing_episodes_raw') {
    table = '#table_cmdMissing';
  }
  if (_cmd.logicalId == 'notification' || _cmd.logicalId == 'notificationHTML') {
    table = '#table_cmdNotifications';
  }
  if (_cmd.logicalId == 'search_action' || _cmd.logicalId == 'search_result' || _cmd.logicalId == 'search_result_raw') {
    table = '#table_cmdSearch';
  }
  if (_cmd.logicalId == 'get_path' || _cmd.logicalId == 'path_result' || _cmd.logicalId == 'path_result_raw') {
    table = '#table_cmdFolder';
  }
  if (_cmd.logicalId == 'get_tags' || _cmd.logicalId == 'tags_result' || _cmd.logicalId == 'tags_result_raw') {
    table = '#table_cmdTags';
  }
  if (_cmd.logicalId == 'get_profiles' || _cmd.logicalId == 'profiles_result' || _cmd.logicalId == 'profiles_result_raw') {
    table = '#table_cmdProfile';
  }
  if (_cmd.logicalId == 'refresh' || _cmd.logicalId == 'monitoredSeries' || _cmd.logicalId == 'add_serie' || _cmd.logicalId == 'search_missing') {
    table = '#table_cmdOther';
  }
  $(table + ' tbody').append(tr);
  $('#table_cmdOrder tbody').append(trOther);

  var tr = $(table + ' tbody tr').last();
  jeedom.eqLogic.builSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function (error) {
      $('#div_alert').showAlert({ message: error.message, level: 'danger' });
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result);
      tr.setValues(_cmd, '.cmdAttr');
      jeedom.cmd.changeType(tr, init(_cmd.subType));
    }
  });

  var tr = $('#table_cmdOrder tbody tr').last();
  jeedom.eqLogic.builSelectCmd({
    id: $('.eqLogicAttr[data-l1key=id]').value(),
    filter: { type: 'info' },
    error: function (error) {
      $('#div_alert').showAlert({ message: error.message, level: 'danger' });
    },
    success: function (result) {
      tr.find('.cmdAttr[data-l1key=value]').append(result);
      tr.setValues(_cmd, '.cmdAttr');
      jeedom.cmd.changeType(tr, init(_cmd.subType));
    }
  });
}

$(".eqLogicAttr[data-l1key='configuration'][data-l2key='application']").change(function () {
  $('.sonarr-function-config').hide();
  $('.sonarr-' + $(this).value()).show();
});

$(".eqLogicAttr[data-l1key='configuration'][data-l2key='groupedEpisodes']").change(function () {
  var groupedEpisodes = $(".eqLogicAttr[data-l1key='configuration'][data-l2key='groupedEpisodes']").value();
  if (groupedEpisodes == 1) {
    checkBox = document.getElementById('info_sup_epGroup').style.display = 'block';
  } else {
    checkBox = document.getElementById('info_sup_epGroup').style.display = 'none';
  }
});

$('.pluginAction[data-action=openLocation]').on('click', function () {
  window.open($(this).attr("data-location"), "_blank", null);
});

