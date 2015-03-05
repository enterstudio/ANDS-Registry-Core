@if($ro->relatedInfo)
<?php
$found = false;
$heading = '<div class="swatch-white">
    <div class="panel panel-primary element-no-top element-short-bottom panel-content">
        <div class="panel-heading">
            <a href="">Reuse Information</a>
        </div>
        <div class="panel-body swatch-white">';
$closure = '        </div>
    </div>
</div>'; ?>

	@foreach($ro->relatedInfo as $relatedInfo)

        @if($relatedInfo['type']=='reuseInformation')

        <?php if(!$found) {echo $heading; $found=true; }?>
        <p>
            {{$relatedInfo['title']}}<br />
            {{$relatedInfo['identifier']['identifier_type']}} :
            @if($relatedInfo['identifier']['identifier_href']['href'])
            <a href="{{$relatedInfo['identifier']['identifier_href']['href']}}">{{$relatedInfo['identifier']['identifier_value']}}</a><br />
            @else
            {{$relatedInfo['identifier']['identifier_value']}}<br />
            @endif
            {{$relatedInfo['notes']}}
        </p>
        @endif
	@endforeach
<?php if($found) {echo $closure;} ?>
@endif

