<head>
	<meta charset="utf-8">
	<title>ANDS Vocabulary Vocabs</title>
		<meta property="og:type" content="article"/>
	<meta content="width=device-width, initial-scale=1.0" name="viewport">
	<meta content="yes" name="apple-mobile-web-app-capable">
	@include('includes/styles')
	@include('includes/scripts')
</head>

<!-- Environment Indicator -->
@if(ENVIRONMENT!='production')
	<div class="environment" style="background:{{get_config_item('environment_colour')}};color:white;padding:5px 10px;">
		<h3>{{get_config_item('environment_name')}} - {{ENVIRONMENT}}</h3>
	</div>
@endif