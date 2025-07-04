<?php
$isMain = strpos($user['call'], '/') === false;
$home = 0;
$park_v = 0;
$park_10b = 0;
$other = 0;
$locations = 0;

$alt_home = 0;
$alt_park_v = 0;
$alt_park_10b = 0;
$alt_other = 0;
$alt_locations = 0;
foreach ($qths as $name => $qth) {
    if ($qth['call'] === $user->call) {
        $home += $qth['home'] ? 1 : 0;
        $locations += 1;
        $park_v += $qth['pota'] || $qth['wwff'] ? 1 : 0;
        $park_10b += $qth['pota'] && $qth['logBands'] >= 10 ? 1 : 0;
        $other += !$qth['pota'] && !$qth['wwff'] && !$qth['home'] ? 1 : 0;
    } else {
        $alt_home += $qth['home'] ? 1 : 0;
        $alt_locations += 1;
        $alt_park_v += $qth['pota'] || $qth['wwff'] ? 1 : 0;
        $alt_park_10b += $qth['pota'] && $qth['logBands'] >= 10 ? 1 : 0;
        $alt_other += !$qth['pota'] && !$qth['wwff'] && !$qth['home'] ? 1 : 0;
    }
}
?>
<x-app-layout>
    @vite([
        'resources/css/summary.css'
    ])
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">{!! $title !!}</h2>
                    @if ($isMain && $locations > 1)
                        <p>@if($locations === 2)
                                Both
                            @else
                                All <b>{{ $locations }}</b>
                            @endif locations
                            @if($park_v)
                                - including
                                <a class="url" target="_blank" href="https://pota.app/#/profile/{{ explode('/',$user->call)[0] }}"><b>{{ $park_v }}</b>
                                    Park{{ $park_v > 1 ? 's' : '' }}</a> -
                            @endif
                            are situated within a radius of
                            <b>{{ round($qth_bounds['radius'] / 1000, 1) }} Km</b>
                            ({{ round(0.6213712 * ($qth_bounds['radius'] / 1000),1) }} Miles)
                            - indicated by the <strong style="color:green" class="toggleLayer" data-layer="ringLocs">green</strong> circle.
                            @if($park_10b)
                                <br>{!! $park_10b === 1 ? '<b>One</b> park' : 'A total of <b>' . $park_10b . '</b> parks' !!}
                                included logs on 10 or more bands, qualifying towards the <a
                                    href="https://docs.pota.app/docs/awards.html#james-f-laporta-n1cc-awards"
                                    target="_blank" class="url">POTA N1CC Award</a>.
                            @endif
                        </p>
                    @endif
    @if($isMain)
                        <p>Most QRZ awards require locations used to qualify be within 50 miles radius of a given point
                            - indicated by the <strong style="color:red" class="toggleLayer" data-layer="ring50">red</strong> circle.<br />
                            Click the labels in the key below to toggle the visibility of the pins of that type on the map.
                        </p>
    @endif
                        <fieldset id="key">
                            <span class="nowrap">
    @if($home)
                                <span class="nowrap toggleLayer" data-layer="home">
                                    <img src="{{ asset('images/orange-pushpin.png') }}" alt="Home QTH" style="height: 30px">Home
                                </span>
    @endif
    @if ($park_v)
                                <span class="nowrap toggleLayer" data-layer="parkVisited">
                                    <img src="{{ asset('images/green-pushpin.png') }}" alt="Visited park with 1-9 bands worked">Visited Park:
                                    <b>{{ $park_v - $park_10b }}</b>
                                </span>
        @if ($park_10b)
                                <span class="nowrap toggleLayer" data-layer="parkN1cc">
                                    <img src="{{ asset('images/lightgreen-pushpin.png') }}" alt="Visited park with 10 bands worked">Park on 10 bands:
                                    <b>{{ $park_10b }}</b>
                                </span>
        @endif
    @endif
    @if ($other)
                                <span class="nowrap toggleLayer" data-layer="other">
                                    <img src="{{ asset('images/yellow-pushpin.png') }}" alt="Other non-park location">Other QTH:
                                    <b>{{ $other }}</b>
                                </span>
    @endif
                            </span>
    @if ($alt_locations)
                            <span class="nowrap toggleLayer" data-layer="otherCallsign">
                                <img src="{{ asset('images/grey-pushpin.png') }}" alt="Visited location worked with other callsign">Other Callsign:
                                <b>{{ $alt_locations }}</b>
                            </span>
    @endif
                            <span class="nowrap">
                                <span class="nowrap toggleLayer" data-layer="parkPota"><img
                                    src="{{ asset('images/red-pushpin.png') }}" alt="POTA Park - Unvisited">POTA Park</span>
                                <span class="nowrap toggleLayer" data-layer="parkWwff"><img
                                    src="{{ asset('images/blue-pushpin.png') }}" alt="WWFF Park - Unvisited">WWFF Park</span>
                                <span class="nowrap toggleLayer" data-layer="parkBoth"><img
                                    src="{{ asset('images/purple-pushpin.png') }}" alt="POTA and WWFF Park - Unvisited">Dual Park</span>
                            </span>
                            <span class="nowrap">
                                <span id="currentLocation" style="display: none">
                                    <a class="url" href="#" id="btnCurrent" title="Click to show your current location">
                                    <img src="{{ asset('images/black-pushpin.png') }}">Your Location</a>:
                                    <input type="text" id="currentGsq">
                                </span>
                            </span>
                    </fieldset>
                    <div id="map" style="height: 600px;">Loading...</div>
                </div>
            </div>
        </div>
    </div>
    <script src="/js/lmap.js?v={{ exec('git describe --tags') }}"></script>
    <script>
        var callsign = "{{ $user['call'] }}";
        var base_image = '/images';
        var bounds = [];
        var box = [{}, {}];
        var gridColor = '#ff0000';
        var gridOpacity = 0.75;
        var gsqs = [];
        var layers = {
            home: [],
            ring50: [],
            ringLocs: [],
            grid: [],
            other: [],
            otherCallsign: [],
            parkVisited: [],
            parkN1cc: [],
            parkPota: [],
            parkWwff: [],
            parkBoth: [],
            squares: [],
            squareLabels: []
        };
        var logDates = ['{{ substr($user->first_log, 0, 10) }}', '{{ substr($user->last_log, 0, 10) }}'];
        var qth = {
            call: "{{ $user['call'] }}",
            gsq: "{{ $user['gsq'] }}",
            loc: "{{ $user['qth'] }}, {{ $user['city'] }}, {{ $user['sp'] }}, {{ $user['itu'] }}",
            lat: {{ $user['lat'] }},
            lng: {{ $user['lon'] }},
            name: "{{ $user['name'] }}",
        }
@if($isMain)
        var drawRing = true;
@else
        var drawRing = false;
@endif
        var qthBounds = {
            lat: {{ $qth_bounds['center'][0] }},
            lng: {{ $qth_bounds['center'][1] }},
            radius: {{ $qth_bounds['radius'] }}
        }
        var locations = [
@foreach($qths as $name => $qth)
            {
                name: "{{ $name
            }}", pota: "{{ $qth['pota']
            }}", wwff: "{{ $qth['wwff']
            }}", home: {{ $qth['home'] ? 1 : 0
            }}, primary: {{ $qth['call'] === $user['call'] ? 1 : 0
            }}, lat: {{ $qth['lat']
            }}, lng: {{ $qth['lon']
            }}, gsq: '{{ $qth['gsq']
            }}', days: {{ $qth['logDays']
            }}, logs: {{ $qth['logs']
            }}, logBands: {{ $qth['logBands']
            }}, logBandNames: "{{ $qth['logBandNames']
            }}"
            },
@endforeach
        ];
        var center = {lat: {{ $qth_bounds['center'][0] }}, lng: {{ $qth_bounds['center'][1] }}}
        window.addEventListener("DOMContentLoaded", () => {
            let script = document.createElement("script");
            script.loading = 'async';
            script.src = "https://maps.googleapis.com/maps/api/js?key={{ getEnv('GOOGLE_MAPS_API_KEY') }}&loading=async&callback=LMap.initLocationsMap";
            document.head.appendChild(script);
        });
    </script>
</x-app-layout>
