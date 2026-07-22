@if ($previous > 0)
    @php
        $pctChange = round((($current - $previous) / $previous) * 100);
        $isUp = $current > $previous;
        $isDown = $current < $previous;
    @endphp
    <div class="comparison">
        @if ($isUp)
            <i class="fa fa-arrow-up"></i> {{ $pctChange }}%
        @elseif ($isDown)
            <i class="fa fa-arrow-down"></i> {{ abs($pctChange) }}%
        @else
            <i class="fa fa-minus"></i> 0%
        @endif
        <span style="opacity: 0.7">vs prev period</span>
    </div>
@elseif ($current > 0)
    <div class="comparison">
        <i class="fa fa-arrow-up"></i> new
        <span style="opacity: 0.7">vs prev period</span>
    </div>
@endif
