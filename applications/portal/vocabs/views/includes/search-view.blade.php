<section class='section swatch-gray'>
	<div class="container element-short-bottom element-short-top">
		<div class="row">

			<div class="col-md-4 col-lg-3 sidebar search-sidebar">

				<div ng-if="facets.subjects">
				<h3>Subject</h3>
					<ul class="list-unstyled">
						<li ng-repeat="facet in facets.subjects.slice(0,8)">

							<a href="" ng-click="toggleFilter('subjects', facet.name, true)">[[ facet.name ]] ([[facet.value]])</a>
							<a href="" ng-click="toggleFilter('subjects', facet.name, true)" ng-if="isFacet('subjects',facet.name)"><i class="fa fa-remove"></i></a>
						</li>

                        <a href="" ng-click="toggleFacet('subjects')" ng-if="facets.subjects.length>8" id="linksubjects"  style="display:block"><small>View More...</small></a>
                        <div id="moresubjects" style="display:none">
                            <li ng-repeat="facet in facets.subjects.slice(8)">
                                <a href="" ng-click="toggleFilter('subjects', facet.name, true)">[[ facet.name ]] ([[facet.value]])</a>
                                <a href="" ng-click="toggleFilter('subjects', facet.name, true)" ng-if="isFacet('subjects',facet.name)"><i class="fa fa-remove"></i></a>

                            </li>
                            <a href="" ng-click="toggleFacet('subjects')" ><small>View Less...</small></a>
                        </div>
                    </ul>
				</div>
				<div ng-if="facets.publisher">
				<h3>Publisher</h3>
   					<ul class="list-unstyled">
						<li ng-repeat="facet in facets.publisher.slice(0,8)">
							<a href="" ng-click="toggleFilter('publisher', facet.name, true)">[[ facet.name ]] ([[facet.value]])</a>
							<a href="" ng-click="toggleFilter('publisher', facet.name, true)" ng-if="isFacet('publisher',facet.name)"><i class="fa fa-remove"></i></a>
						</li>
                        <a href="" ng-click="toggleFacet('publisher')" ng-if="facets.publisher.length>8" id="linkpublisher"  style="display:block"><small>View More...</small></a>
                        <div id="morepublisher" style="display:none">
                            <li ng-repeat="facet in facets.publisher.slice(8)">
                                <a href="" ng-click="toggleFilter('publisher', facet.name, true)">[[ facet.name ]] ([[facet.value]])</a>
                                <a href="" ng-click="toggleFilter('publisher', facet.name, true)" ng-if="isFacet('publisher',facet.name)"><i class="fa fa-remove"></i></a>
                            </li>
                            <a href="" ng-click="toggleFacet('publisher')" ><small>View Less...</small></a>
                        </div>
					</ul>
				</div>
				<div ng-if="facets.language">
				<h3>Language</h3>
					<ul class="list-unstyled">
						<li ng-repeat="facet in facets.language.slice(0,8)">
							<a href="" ng-click="toggleFilter('language', facet.name, true)">[[ facet.name ]] ([[facet.value]])</a>
							<a href="" ng-click="toggleFilter('language', facet.name, true)" ng-if="isFacet('language',facet.name)"><i class="fa fa-remove"></i></a>
						</li>
                        <a href="" ng-click="toggleFacet('language')" ng-if="facets.language.length>8" id="linklanguage"  style="display:block"><small>View More...</small></a>
                        <div id="morelanguage" style="display:none">
                            <li ng-repeat="facet in facets.language.slice(8)">
                                <a href="" ng-click="toggleFilter('language', facet.name, true)">[[ facet.name ]] ([[facet.value]])</a>
                                <a href="" ng-click="toggleFilter('language', facet.name, true)" ng-if="isFacet('language',facet.name)"><i class="fa fa-remove"></i></a>
                            </li>
                            <a href="" ng-click="toggleFacet('language')" ><small>View Less...</small></a>
                        </div>
                    </ul>
				</div>
				<div ng-if="facets.format">
				<h3>Format</h3>
					<ul class="list-unstyled">
						<li ng-repeat="facet in facets.format">
							<a href="" ng-click="toggleFilter('format', facet.name, true)">[[ facet.name ]] ([[facet.value]])</a>
							<a href="" ng-click="toggleFilter('format', facet.name, true)" ng-if="isFacet('format',facet.name)"><i class="fa fa-remove"></i></a>
						</li>
					</ul>
				</div>
				<div ng-if="facets.access">
				<h3>Access</h3>
					<ul class="list-unstyled">
						<li ng-repeat="facet in facets.access">
							<a href="" ng-click="toggleFilter('access', facet.name, true)">[[ facet.name ]] ([[facet.value]])</a>
							<a href="" ng-click="toggleFilter('access', facet.name, true)" ng-if="isFacet('access',facet.name)"><i class="fa fa-remove"></i></a>
						</li>
					</ul>
				</div>
				<div ng-if="facets.licence">
				<h3>Licence</h3>
					<ul class="list-unstyled">
						<li ng-repeat="facet in facets.licence">
							<a href="" ng-click="toggleFilter('licence', facet.name, true)">[[ facet.name ]] ([[facet.value]])</a>
							<a href="" ng-click="toggleFilter('licence', facet.name, true)" ng-if="isFacet('licence',facet.name)"><i class="fa fa-remove"></i></a>
						</li>
					</ul>
				</div>
			</div>

			<div class="col-md-8 col-lg-9">

				<div ng-repeat="doc in result.response.docs" class="animated fadeInLeft vocab-search-result">
					<h3><a href="[[ base_url ]][[ doc.slug ]]">[[ doc.title ]]</a></h3>
					<p ng-if="doc.acronym">
						<small>Acronym: [[ doc.acronym ]]</small>
					</p>
					<p ng-if="doc.publisher">
						Publisher: [[ doc.publisher.join(',') ]]
					</p>
					<p ng-if="getHighlight(doc.id)===false">[[ doc.description | limitTo:500 ]]<span ng-if="doc.description.length > 500">...</span></p>
					<div ng-repeat="(index, content) in getHighlight(doc.id)" class="element-shorter-bottom">
	                    <div ng-repeat="c in content track by $index" class="element-shortest-bottom">
	                        <span ng-bind-html="c | trustAsHtml"></span> <span class="muted">(in [[ index | removeSearchTail ]])</span>
	                    </div>
	                </div>
				</div>

				<div ng-if="result.response.numFound == 0" class="animated fadeInLeft vocab-search-result">
					Your search did not return any results
				</div>
			</div>

		</div>

	</div>
</section>