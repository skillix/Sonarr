<?php
if (!isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
// Déclaration des variables obligatoires
$plugin = plugin::byId('sonarr');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
	<!-- Page d'accueil du plugin -->
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<!-- Boutons de gestion du plugin -->
		<div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction logoPrimary" data-action="add">
				<i class="fas fa-plus-circle"></i>
				<br>
				<span>{{Ajouter}}</span>
			</div>
			<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
				<i class="fas fa-wrench"></i>
				<br>
				<span>{{Configuration}}</span>
			</div>
			<div class="cursor pluginAction logoSecondary" data-action="openLocation" data-location="https://github.com/users/hbedek/projects/4">
                <i class="fas fa-columns"></i>
                <br>
                <span>Agenda des fonctionnalités</span>
            </div>
		</div>
		<legend><i class="fas fa-table"></i> {{Mes serveurs}}</legend>
		<?php
		if (count($eqLogics) == 0) {
			echo '<br/><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement Template n\'est paramétré, cliquer sur "Ajouter" pour commencer}}</div>';
		} else {
			// Champ de recherche
			echo '<div class="input-group" style="margin:5px;">';
			echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic"/>';
			echo '<div class="input-group-btn">';
			echo '<a id="bt_resetSearch" class="btn roundedRight" style="width:30px"><i class="fas fa-times"></i></a>';
			echo '</div>';
			echo '</div>';
			// Liste des équipements du plugin
			echo '<div class="eqLogicThumbnailContainer">';
			foreach ($eqLogics as $eqLogic) {
				$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
				echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
				echo '<img src="' . $eqLogic->getImage() . '"/>';
				echo '<br>';
				echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
				echo '</div>';
			}
			echo '</div>';
		}
		?>
	</div> <!-- /.eqLogicThumbnailDisplay -->

	<!-- Page de présentation de l'équipement -->
	<div class="col-xs-12 eqLogic" style="display: none;">
		<!-- barre de gestion de l'équipement -->
		<div class="input-group pull-right" style="display:inline-flex;">
			<span class="input-group-btn">
				<!-- Les balises <a></a> sont volontairement fermées à la ligne suivante pour éviter les espaces entre les boutons. Ne pas modifier -->
				<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
				</a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span>
				</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
				</a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
				</a>
			</span>
		</div>
		<!-- Onglets -->
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content">
			<!-- Onglet de configuration de l'équipement -->
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<!-- Partie gauche de l'onglet "Equipements" -->
				<!-- Paramètres généraux de l'équipement -->
				<form class="form-horizontal">
					<fieldset>
						<div class="col-lg-6">
							<legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Nom de l'équipement}}</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
									<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}" />
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Objet parent}}</label>
								<div class="col-sm-7">
									<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
										<option value="">{{Aucun}}</option>
										<?php
										$options = '';
										foreach ((jeeObject::buildTree(null, false)) as $object) {
											$options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
										}
										echo $options;
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Catégorie}}</label>
								<div class="col-sm-7">
									<?php
									foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
										echo '<label class="checkbox-inline">';
										echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
										echo '</label>';
									}
									?>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Options}}</label>
								<div class="col-sm-7">
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked />{{Activer}}</label>
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked />{{Visible}}</label>
								</div>
							</div>

							<legend><i class="fas fa-cogs"></i> {{Paramètres spécifiques}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Sonarr/Radarr}}</label>
								<div class="col-sm-3">
									<select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="application">
										<option value="">{{Sélectionner}}</option>
										<?php
										foreach (sonarr::getApplications() as $key => $description) {
											echo "<option value='{$key}'>{$description}</option>";
										}
										?>
									</select>
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<label class="col-sm-3 control-label">{{Url de Sonarr}}</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="sonarrUrl" placeholder="{{http://127.0.0.1:8989}}" />
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-radarr">
								<label class="col-sm-3 control-label">{{Url de Radarr}}</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="radarrUrl" placeholder="{{http://127.0.0.1:8310}}" />
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label"> {{API KEY}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Renseignez l'api Key de votre application}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control inputPassword" data-l1key="configuration" data-l2key="apiKey" />
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label"> {{Widget format condensé}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Possibilité d'affichage du widget en condensé}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="condensedWidget" checked />{{format condensé}}</label>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Auto-actualisation}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Fréquence de rafraîchissement de l'équipement}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<div class="input-group">
										<input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="autorefresh" placeholder="{{Cliquer sur ? pour afficher l'assistant cron}}" />
										<span class="input-group-btn">
											<a class="btn btn-default cursor jeeHelper roundedRight" data-helper="cron" title="Assistant cron">
												<i class="fas fa-question-circle"></i>
											</a>
										</span>
									</div>
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<label class="col-sm-3 control-label"> {{Regroupement des épisodes}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Possibilité de regrouper les épisodes téléchargé d'une même saison.}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="groupedEpisodes" />{{Regrouper les épisodes}}</label>
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<div id="info_sup_epGroup">
									<label class="col-sm-3 control-label"> {{Séparateur d'épisode}} </label>
									<div class="col-sm-3">
										<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="separatorEpisodes" placeholder=", " />
									</div>
								</div>
							</div>
							<legend><i class="fas fa-cogs"></i> {{Configuration épisodes / films à venir}}</legend>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<label class="col-sm-3 control-label"> {{Nombre de jours à remonter}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Par défaut le plugin retourne les objets de ce jour ci}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="dayFutureEpisodes" placeholder="1" />
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-radarr">
								<label class="col-sm-3 control-label"> {{Nombre de jours à remonter}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Par défaut le plugin retourne les objets de ce jour ci}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="dayFutureMovies" placeholder="1" />
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<label class="col-sm-3 control-label"> {{Nombre d'objets max à remonter}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Si non renseigné, seule la règle du nombre de jours compte}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="maxFutureEpisodes" placeholder="1" />
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-radarr">
								<label class="col-sm-3 control-label"> {{Nombre d'objets max à remonter}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Si non renseigné, seule la règle du nombre de jours compte}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="maxFutureMovies" placeholder="1" />
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<legend><i class="fas fa-cogs"></i> {{Configuration épisodes / films manquants}}</legend>
								<label class="col-sm-3 control-label"> {{Nombre de jours à remonter}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Par défaut le plugin retourne les objets de ce jour ci}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="dayMissingEpisodes" placeholder="1" />
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<label class="col-sm-3 control-label"> {{Nombre d'objets max à remonter}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Si non renseigné, seule la règle du nombre de jours compte}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="maxMissingEpisodes" placeholder="1" />
								</div>
							</div>
							<legend><i class="fas fa-cogs"></i> {{Configuration épisodes / films téléchargés}}</legend>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<label class="col-sm-3 control-label"> {{Nombre de jours à remonter}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Par défaut le plugin retourne les objets de ce jour ci}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="dayDownloadedEpisodes" placeholder="1" />
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-radarr">
								<label class="col-sm-3 control-label"> {{Nombre de jours à remonter}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Par défaut le plugin retourne les objets de ce jour ci}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="dayDownloadedMovies" placeholder="1" />
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<label class="col-sm-3 control-label"> {{Nombre d'objets max à remonter}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Si non renseigné, seule la règle du nombre de jours compte}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="maxDownloadedEpisodes" placeholder="1" />
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-radarr">
								<label class="col-sm-3 control-label"> {{Nombre d'objets max à remonter}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Si non renseigné, seule la règle du nombre de jours compte}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="maxDownloadedMovies" placeholder="1" />
								</div>
							</div>
							<legend><i class="fas fa-cogs"></i> {{Configuration supplémentaire}}</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label"> {{Séparateur à utiliser}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Vous pouvez sélectionner ici un séparateur à utiliser pour le retour des objets}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="separator" placeholder=", " />
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<label class="col-sm-3 control-label"> {{Formatage du nom des épisodes}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Ici, vous pouvez configurer le formatage du nom des épisodes. Le chiffre de la saison est représenté par %s, le chiffre de l'épisode est représenté par %e. Si vous souhaitez que l'équipement retourne l'épisode sous la forme saison 2 épisode 4, Il faut alors renseigner saison %s épisode %e dans le champ}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="formattorEpisode" placeholder="S%sE%e" />
								</div>
							</div>
						</div>
					</fieldset>
				</form>
				<hr>
			</div><!-- /.tabpanel #eqlogictab-->

			<!-- Onglet des commandes de l'équipement -->
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<a class="btn btn-default btn-sm pull-right cmdAction" data-action="add" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}}</a>
				<br /><br />
				<div class="table-responsive">
					<table id="table_cmd" class="table table-bordered table-condensed">
						<thead>
							<tr>
								<th>{{Id}}</th>
								<th>{{Nom}}</th>
								<th>{{Type}}</th>
								<th>{{Paramètres}}</th>
								<th>{{Options}}</th>
								<th>{{Action}}</th>
							</tr>
						</thead>
						<tbody>
						</tbody>
					</table>
				</div>
			</div><!-- /.tabpanel #commandtab-->

		</div><!-- /.tab-content -->
	</div><!-- /.eqLogic -->
</div><!-- /.row row-overflow -->

<!-- Inclusion du fichier javascript du plugin (dossier, nom_du_fichier, extension_du_fichier, id_du_plugin) -->
<?php include_file('desktop', 'sonarr', 'js', 'sonarr'); ?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js'); ?>