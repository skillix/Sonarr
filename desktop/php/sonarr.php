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
		<legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
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
		</div>
		<legend><i class="fas fa-table"></i> {{Mes templates}}</legend>
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
				echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
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
				</a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs">  {{Dupliquer}}</span>
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
									<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;"/>
									<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label" >{{Objet parent}}</label>
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
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
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
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="sonarrUrl" placeholder="{{http://127.0.0.1:8989}}"/>
                                </div>
                            </div>
							<div class="form-group sonarr-function-config sonarr-radarr">
                                <label class="col-sm-3 control-label">{{Url de Radarr}}</label>
                                <div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="radarrUrl" placeholder="{{http://127.0.0.1:8310}}"/>
                                </div>
                            </div>
							<div class="form-group">
								<label class="col-sm-3 control-label"> {{API KEY}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Renseignez l'api Key de votre application'}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control inputPassword" data-l1key="configuration" data-l2key="apiKey"/>
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<label class="col-sm-3 control-label"> {{Nombre d'épisode à remonter pour la liste des épisodes manquants et téléchargés}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Renseignez le nombre d'épisodes que vous souhaitez voir remonter. Agit sur les épisodes téléchargés et les épisodes manquant}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="numberEpisodes" placeholder="5"/>
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-radarr">
								<label class="col-sm-3 control-label"> {{Nombre de films à remonter pour la liste des films téléchargés}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Renseignez le nombre de films que vous souhaitez voir remonter. Agit sur les films téléchargés}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="numberMovies" placeholder="5"/>
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-sonarr">
								<label class="col-sm-3 control-label"> {{Nombre de jours pour les épisodes futures}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Renseigez le nombre de jours pour lequel le plugin va récupérer les épisodes futures. Par défaut le plugin récupère la liste des épisodes sortant ce jour}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="dayFutureEpisodes" placeholder="1"/>
								</div>
							</div>
							<div class="form-group sonarr-function-config sonarr-radarr">
								<label class="col-sm-3 control-label"> {{Nombre de jours pour les films futures}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Renseigez le nombre de jours pour lequel le plugin va récupérer les films futures. Par défaut le plugin récupère la liste des films sortant ce jour}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="dayFutureMovies" placeholder="1"/>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label"> {{Séparateur à utiliser}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Vous pouvez sélectionner ici un séparateur à utiliser pour le retour des épisodes}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="separator" placeholder=", "/>
								</div>
							</div>
							<!-- Champ de saisie du cron d'auto-actualisation + assistant cron -->
							<!-- La fonction cron de la classe du plugin doit contenir le code prévu pour que ce champ soit fonctionnel -->
							<div class="form-group">
								<label class="col-sm-3 control-label">{{Auto-actualisation}}
									<sup><i class="fas fa-question-circle tooltips" title="{{Fréquence de rafraîchissement de l'équipement}}"></i></sup>
								</label>
								<div class="col-sm-7">
									<div class="input-group">
										<input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="autorefresh" placeholder="{{Cliquer sur ? pour afficher l'assistant cron}}"/>
										<span class="input-group-btn">
											<a class="btn btn-default cursor jeeHelper roundedRight" data-helper="cron" title="Assistant cron">
												<i class="fas fa-question-circle"></i>
											</a>
										</span>
									</div>
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
				<br/><br/>
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
<?php include_file('desktop', 'sonarr', 'js', 'sonarr');?>
<!-- Inclusion du fichier javascript du core - NE PAS MODIFIER NI SUPPRIMER -->
<?php include_file('core', 'plugin.template', 'js');?>
