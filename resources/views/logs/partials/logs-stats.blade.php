<div class="stats" id="stats" style="display:none">
    <p class="quicklinks" id="top">Jump To [
        <a href="#rptUsCounties">US Counties</a> |
        <a href="#rptCountries">Countries</a>
    ]</p>

    <div id="rptUsCounties">
        <h2>{{ date('Y-m-d') }} Confirmed US Counties by State: <span class="quicklinks">[
            <a href="#stats">Top</a> |
            <a href="#" id="usCountiesStatePrint">Print</a>
        ]</span></h2>
        <div id="usCountiesTotal"></div>
        <div id="usCountiesState"><div class="statsloader"></div></div>
    </div>

    <div id="rptCountries">
    <h2>{{ date('Y-m-d') }} Confirmed Countries: <span class="quicklinks">[
        <a href="#stats">Top</a> |
        <a href="#" id="countriesPrint">Print</a>
    ]</span></h2>
    <p id="countriesTotal"></p>
    <div id="countries"><div class="statsloader"></div></div>
    </div>

</div>
