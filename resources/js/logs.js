var filters = {
    bands: [],
    modes: [],
    conf: '',
    cont: '',
    call: '',
    dateFrom: '',
    dateTo: '',
    myQth: '',
    sp: '',
    itu: '',
    gsq: '',
    showAll: false,
    showMax: 100
}
var logs = [];
var logsFiltered = [];

var COOKIE = {
    clear: function(which, path) {
        document.cookie =
            which +
            '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=' +
            ('string' === typeof path ? path : '/');
    },
    get: function(which) {
        var cookies =		document.cookie;
        var pos =		cookies.indexOf(which+"=");
        if (pos === -1) {
            return false;
        }
        var start =	pos + which.length+1;
        var end =	cookies.indexOf(";",start);
        if (end === -1) {
            end =	cookies.length;
        }
        return unescape(cookies.substring(start, end));
    },
    set: function(which, value, path) {
        var nextYear =	new Date();
        nextYear.setFullYear(nextYear.getFullYear()+1);
        document.cookie =
            which +
            '=' + value + ';expires=' + nextYear.toGMTString() + '; path=' +
            ('string' === typeof path ? path : '/');
    },
}

var frm = {
    start: null,

    _init: () => {
        if (typeof callsign !== 'undefined') {
            frm.setActions();
            frm.compact();
            frm.load(callsign)
        }
    },

    addLinks: () => {
        $('td[data-link], span[data-link]').each(function() {
            let link = $(this).attr('data-link');
            let html = $("<a href=\"#\">" + $(this).html() + "</a>");
            $(html).on('click', function() {
                return frm.setVal(link, $(this).text().split('(')[0].trim());
            })
            $(this).html(html);
        })
    },

    cl: function(v) {
        console.log(v);
    },

    compact: () => {
        let compact = COOKIE.get('compact');
        'Y' === compact ? $('.not-compact').hide() : $('.not-compact').show();
        'Y' === compact ? $('main').addClass('compact') : $('main').removeClass('compact');
        $(window).trigger('resize');
    },

    count: () => {
        let all = logs.length;
        let shown = logsFiltered.length;
        if (filters.showMax >= shown) {
            filters.showAll = true;
        }
        let msg = 'Showing ' +
            (all === shown ?
                (filters.showAll ?
                    'all'
                :
                    'first <b>' + filters.showMax + '</b> of'
                ) + ' <b>' + shown
            :
                '<b>' + shown + '</b> of <b>' + all
            ) +
            '</b> log' + (shown ===1 ? '' : 's');
        $('#logCount').html(msg);
        if (!filters.showAll) {
            $("#showAll").show();
        }
    },

    getFilters: () => {
        filters.bands = [];
        filters.modes = [];
        $('.band input:checked').each(function () {
            filters.bands.push($(this).data('band'));
        });
        $('.mode input:checked').each(function () {
            filters.modes.push($(this).data('mode'));
        });
        filters.conf =      $('input[name=conf]:checked').val();
        filters.myQth =     $('select[name=myQth]').val();
        filters.call =      $('input[name=call]').val();
        filters.dateFrom =  $('input[name=dateFrom]').val();
        filters.dateTo =    $('input[name=dateTo]').val();
        filters.sp =        $('input[name=sp]').val();
        filters.itu =       $('input[name=itu]').val();
        filters.cont =      $('select[name=cont]').val();
        filters.gsq =       $('input[name=gsq]').val();
    },

    getGridSquares: ()  => {
        gsqs = [] // Global
        let gsqs_tmp = {}
        let lat_min = 90;
        let lat_max = -90;
        let lon_min = 180;
        let lon_max = -180

        $(logsFiltered).each(function(idx,log){
            let gsq = log.gsq;
            let latlon;
            if (gsq) {
                if (typeof gsqs_tmp[gsq] === 'undefined') {
                    latlon = frm.gsq_deg(gsq);
                    gsqs_tmp[gsq] = {
                        bands: [],
                        bands_count: 0,
                        bands_html: '',
                        calls: [],
                        calls_count: 0,
                        calls_html: '',
                        conf_qc: '',
                        deg: 0,
                        gsq: gsq,
                        km: 0,
                        lat: latlon.lat,
                        lon: latlon.lon,
                        logs: [],
                        logs_count: 0,
                        marker: null
                    };
                    if (latlon.lat < 85 && latlon.lat > -85) {
                        if (latlon.lat > lat_max) {
                            lat_max = latlon.lat;
                        }
                        if (latlon.lat < lat_min) {
                            lat_min = latlon.lat;
                        }
                        if (latlon.lon > lon_max) {
                            lon_max = latlon.lon;
                        }
                        if (latlon.lon < lon_min) {
                            lon_min = latlon.lon;
                        }
                        box = [{
                            lat: lat_min,
                            lon: lon_min
                        }, {
                            lat: lat_max,
                            lon: lon_max
                        }];
                    }
                }
                // log.conf_qc: QRZ = '1', Clublog = '2', unconfirmed = ''

                if (gsqs_tmp[gsq].conf_qc !== '1') {
                    // Not already confirmed in QRZ
                    if (log.conf_qc === '2') {
                        gsqs_tmp[gsq].conf_qc = '2';
                    }
                    if (log.conf_qc === '1') {
                        gsqs_tmp[gsq].conf_qc = '1';
                    }
                }
                gsqs_tmp[gsq].logs.push(log);
                gsqs_tmp[gsq].bands[log.band] = log.band;
                gsqs_tmp[gsq].calls[log.call] = log.call;
            }
        });
        for (let gsq in gsqs_tmp) {
            gsqs_tmp[gsq].logs_count = gsqs_tmp[gsq].logs.length;
            gsqs_tmp[gsq].bands = LMap.getUniqueArrayValues(gsqs_tmp[gsq].bands).sort(LMap.sortBands);
            gsqs_tmp[gsq].bands_count = gsqs_tmp[gsq].bands.length;
            for (let i=0; i < gsqs_tmp[gsq].bands.length; i++) {
                gsqs_tmp[gsq].bands_html += "<span class='band band" + gsqs_tmp[gsq].bands[i] + "'>" + gsqs_tmp[gsq].bands[i] + "</span>";
            }
            gsqs_tmp[gsq].calls = LMap.getUniqueArrayValues(gsqs_tmp[gsq].calls).sort(LMap.sortCalls);
            gsqs_tmp[gsq].calls_count = gsqs_tmp[gsq].calls.length
            let calls_arr = [];
            for (let i=0; i <gsqs_tmp[gsq].calls.length; i++) {
                calls_arr.push(gsqs_tmp[gsq].calls[i]);
            }
            gsqs_tmp[gsq].calls_html = calls_arr.join(', ');
            gsqs.push(gsqs_tmp[gsq]);
        }
        LMap.fitToBox();
    },

    getQsos:(qsos)  => {
        let bands = [];
        let tmp = qsos.split(',');
        let band = '';
        let num = 0;
        let units = 0;
        let val = 0;
        let html = '';
        let text = [];

        let count = tmp.length;
        for (var i=0; i < count; i++) {
            band = tmp[i];
            num = band.replace(/[^0-9\.]/, '');
            units = band.replace(/[^a-zA-Z]/, '');
            val = num * (units === 'm' ? 100 : 1);
            if (typeof bands[val] === 'undefined') {
                bands[val] = { band: band, count: 0 };
            }
            bands[val].count++;
        }
        bands.sort();
        for (i in bands) {
            html +=
                '<span data-link="band"><span class="band band' + bands[i].band + '">' +
                bands[i].band +
                (bands[i].count > 1 ? ' <i>(' + bands[i].count + ')</i>' : '') +
                '</span></span>';
            text.push(bands[i].band + (bands[i].count > 1 ? ' (' + bands[i].count + ')' : ''));
        }
        return { count: count, html: html, text: text.join(', ') };
    },

    getStats: async () => {
        await frm.getStatsUsCounties();
        await frm.getStatsCountries();
    },

    getStatsCountries: async () => {
        var stats = {};
        await $.ajax({
            type: 'GET',
            url: '/stats/' + callsign.replace('/', '-') + '/countries',
            dataType: 'json',
            data: stats,
            success: function (result) {
                stats.countries = result.data;
            }
        })
        let html = '', column = 0, row = 0, i, countries = 0, confirmed = 0, logged = 0;

        for (row = 0; row + column < stats.countries.length + 10; row += 10) {
            html += "<table style='width: 100%'><tr><th>Country</th>";
            for (column = 0; column < 10; column++) {
                if (typeof stats.countries[row+column] === 'undefined') {
                    break;
                }
                html += "<th style='width: 9%'>" + stats.countries[row+column]['country'] + "</th>";
            }
            html += "</tr>";
            html += "<tr><th>Logged</th>";
            for (column = 0; column < 10; column++) {
                if (typeof stats.countries[row+column] === 'undefined') {
                    break;
                }
                html += "<td class='" + (stats.countries[row+column]['logged'] === 0 ? 'pc0' : 'pc100') + "'>" +
                    stats.countries[row+column]['logged'] + "</td>";
                logged += (stats.countries[row+column]['logged'] > 0 ? 1 : 0);
            }
            html += "</tr>";
            html += "<tr><th>Confirmed</th>";
            for (column = 0; column < 10; column++) {
                if (typeof stats.countries[row+column] === 'undefined') {
                    break;
                }
                html += "<td class='" +
                    (stats.countries[row+column]['confirmed'] === 0 ? 'pc0' : 'pc100') +
                    (stats.countries[row+column]['logged'] !== stats.countries[row+column]['confirmed'] ? ' confIssues' : '') +
                    "'>" +
                    stats.countries[row+column]['confirmed'] + "</td>";
                confirmed += (stats.countries[row+column]['confirmed'] > 0 ? 1 : 0);
            }
            html += "</tr>";
            html += "</tr></table>";
        }
        $('#countries').html(html);
        $('#countriesTotal').html(
            'There are <b>' + confirmed + '</b> confirmed ' + (confirmed === 1 ? 'country' : 'countries') +
            ' and <b>' + logged + '</b> logged '+ (logged === 1 ? 'country' : 'countries') +
            ' from a total of <b>' + stats.countries.length + '</b> available ' +
            ' - assuming that there are no problems with qualifying logs at QRZ.com.'
        );
        $("#countriesPrint").click(function(){
            PrintElements.print([document.getElementById('rptCountries')]);
            return false;
        });
    },

    getStatsUsCounties: async () => {
        var stats = {};
        await $.ajax({
            type: 'GET',
            url: '/stats/' + callsign.replace('/', '-') + '/usCounties',
            dataType: 'json',
            data: stats,
            success: function (result) {
                stats.usCounties = result.data;
            }
        })
        let html = '', column = 0, row = 0, i, counties = 0, countiesTotal = 0;
        let dc = false, usState = false, states = 0, unconfirmed = 0;
        let wrongSpLogs = '', wrongSpCount = 0;
        for (i = 0; i < stats.usCounties.length; i++) {
            countiesTotal += stats.usCounties[i].total;
        }

        for (row = 0; row + column < stats.usCounties.length + 10; row += 10) {
            html += "<table><tr><th>State</th>";
            for (column = 0; column < 10; column++) {
                if (typeof stats.usCounties[row+column] === 'undefined') {
                    break;
                }
                dc = stats.usCounties[row+column]['sp'] === 'DC';
                usState = stats.usCounties[row+column]['itu'] === 'USA' ||
                    stats.usCounties[row+column]['sp'] === 'AK' ||
                    stats.usCounties[row+column]['sp'] === 'HI';
                states += (usState && !dc ? 1 : 0);
                html += "<th" +
                    (dc ?
                            ' style="cursor:help; font-style:italic" title="According to QRZ\'s rules, a log in DC counts towards MD for the \'USA 50\' United States Award"'
                            : (!usState ? ' style="cursor:help; font-style:italic" title="This state does not count towards the QRZ \'USA 50\' United States Award"' : '')
                    ) +
                    ">" +
                    stats.usCounties[row+column]['sp'] + (usState && !dc ? '' : ' *') + "</th>";
            }
            html += "</tr>";
            html += "<tr><th>Logged</th>";
            for (column = 0; column < 10; column++) {
                if (typeof stats.usCounties[row+column] === 'undefined') {
                    break;
                }
                html += "<td class='" +
                    (stats.usCounties[row+column]['percent'] === 0 ? 'pc0' : '') +
                    (stats.usCounties[row+column]['percent'] >= 50 && stats.usCounties[row+column]['percent'] < 100 ? 'pc50' : '') +
                    (stats.usCounties[row+column]['percent'] === 100 ? 'pc100' : '') +
                    "'>" + stats.usCounties[row+column]['logged'] + "</td>";
            }
            html += "</tr>";
            html += "<tr><th style='white-space: nowrap'>Wrong State</thstyle>";
            for (column = 0; column < 10; column++) {
                if (typeof stats.usCounties[row+column] === 'undefined') {
                    break;
                }
                wrongSpCount = stats.usCounties[row+column]['wrongSpCount'];
                html += "<td class='" +
                    (wrongSpCount === 0 ? '' : 'wrongSp') +
                    "'" +
                    (wrongSpCount ? " title='There " +
                        (wrongSpCount === 1 ? "is one county in the wrong state:\n\n" : "are " + wrongSpCount + " counties in the wrong state:\n\n") +
                        stats.usCounties[row+column]['wrongSpLogs']
                        :
                    '') + "'>" + wrongSpCount + "</td>";
            }
            html += "</tr>";
            html += "<tr><th>Confirmed</th>";
            for (column = 0; column < 10; column++) {
                if (typeof stats.usCounties[row+column] === 'undefined') {
                    break;
                }
                unconfirmed = stats.usCounties[row+column]['logged'] - stats.usCounties[row+column]['confirmed'];
                html += "<td class='" +
                    (stats.usCounties[row+column]['percent'] === 0 ? 'pc0' : '') +
                    (stats.usCounties[row+column]['percent'] >= 50 && stats.usCounties[row+column]['percent'] < 100 ? 'pc50' : '') +
                    (stats.usCounties[row+column]['percent'] === 100 ? 'pc100' : '') +
                    (unconfirmed ? ' confIssues' : '') +
                    "'" +
                    (unconfirmed ? " title='There " +
                        (unconfirmed === 1 ? "is one unconfirmed county" : "are " + unconfirmed + " unconfirmed counties") : '') +
                    "'>" + stats.usCounties[row+column]['confirmed'] + "</td>";
                counties += stats.usCounties[row+column]['confirmed'];
            }
            html += "</tr>";
            html += "<tr><th>Available</th>";
            for (column = 0; column < 10; column++) {
                if (typeof stats.usCounties[row+column] === 'undefined') {
                    break;
                }
                html += "<td" +
                    (stats.usCounties[row+column]['percent'] === 0 ? ' class="pc0"' : '') +
                    (stats.usCounties[row+column]['percent'] >= 50 && stats.usCounties[row+column]['percent'] < 100 ? ' class="pc50"' : '') +
                    (stats.usCounties[row+column]['percent'] === 100 ? ' class="pc100"' : '') +
                    ">" +
                    stats.usCounties[row+column]['total'] + "</td>";
            }
            html += "</tr>";
            html += "<tr><th>Percent</th>";
            for (column = 0; column < 10; column++) {
                if (typeof stats.usCounties[row+column] === 'undefined') {
                    break;
                }
                html += "<td class='b" +
                    (stats.usCounties[row+column]['percent'] === 0 ? ' pc0' : '') +
                    (stats.usCounties[row+column]['percent'] >= 50 && stats.usCounties[row+column]['percent'] < 100 ? ' pc50' : '') +
                    (stats.usCounties[row+column]['percent'] === 100 ? ' pc100' : '') +
                    "'>" +
                    stats.usCounties[row+column]['percent'] + "%</td>";
            }
            html += "</tr></table>";
        }
        $('#usCountiesState').html(html);
        $('#usCountiesTotal').html(
            'There are <b>' + counties + '</b> confirmed ' + (counties === 1 ? 'county' : 'counties') +
            ' from <b>' + countiesTotal + '</b> available in <b>' + states + '</b> US ' + (states === 1 ? 'state' : 'states') +
            ' - assuming no issues with qualifying logs at <a href="https://qrz.com" class="link" target="_blank">QRZ.com</a><br>' +
            'For the QRZ <b>"US-50"</b> states award, <b>PR</b> and <b>VI</b> logs are ignored and logs for <b>DC</b> count towards <b>MD</b>.'
        );
        $('#usCountiesStatePrint').click(function() {
            PrintElements.print([document.getElementById('rptUsCounties')]);
            return false;
        });
    },

    getUniqueValues: (field) => {
        let idx;
        let tmp = [];
        let count = 0;
        let values = [];
        $(logsFiltered).each(function(idx,log){
            if (log[field] !== '') {
                tmp[log[field].toUpperCase()] = 1;
            }
        });
        for (idx in tmp) {
            if (tmp.hasOwnProperty(idx)) {
                count++;
                values.push(idx)
            }
        }
        return {
            count: count,
            values: values.sort()
        };
    },

    getUniqueValuesStats: (field) => {
        let tmp = frm.getUniqueValues(field);
        return "<span title='" + tmp.values.join(', ') + "' style='cursor: help'>" + tmp.count + "</span>";
    },

    gsq_deg: (GSQ) => {
        let lat, lat_d, lat_m, lat_s, lon, lon_d, lon_m, lon_s, offset;
        if (!GSQ.match(/^([a-rA-R]{2})([0-9]{2})([a-xA-X]{2})?$/i)) {
            return false;
        }
        GSQ = GSQ.toUpperCase();
        offset = 0;//(GSQ.length === 6 ? 1/48 : 0);
        GSQ = GSQ + (GSQ.length === 4 ? 'MM' : '');
        lon_d = GSQ.charCodeAt(0)-65;
        lon_m = parseFloat(GSQ.substring(2,3));
        lon_s = GSQ.charCodeAt(4)-65;
        lat_d = GSQ.charCodeAt(1)-65;
        lat_m = parseFloat(GSQ.substring(3,4));
        lat_s = GSQ.charCodeAt(5)-65;
        lon = Math.round((2 * (lon_d * 10 + lon_m + lon_s / 24 + offset) - 180) * 10000) / 10000;
        lat = Math.round((lat_d * 10 + lat_m + lat_s / 24 + offset - 90) * 10000) / 10000;
        return {
            lat: lat,
            lon: lon,
            north: lat + 0.5,
            south: lat - 0.5,
            east: lon +1,
            west: lon -1
        };
    },

    isVisible: (log) => {
        if (!filters.bands.length || $.inArray(log.band, filters.bands) < 0) {
            return false;
        }
        if (!log.mode.length || $.inArray(log.mode, filters.modes) < 0) {
            return false;
        }
        if (filters.conf === 'N' && log.conf_qc !== '') {
            return false;
        }
        if (filters.conf === 'Y' && log.conf_qc === '') {
            return false;
        }
        if (filters.conf === 'Q' && log.conf_qc !== '1') {
            return false;
        }
        if (filters.conf === 'C' && log.conf_qc !== '2') {
            return false;
        }
        if (filters.call.length && filters.call.toLowerCase() !== log.call.toLowerCase().substring(0, filters.call.length)) {
            return false;
        }
        if (filters.dateFrom.length && filters.dateFrom > log.date.substring(0, filters.dateFrom.length)) {
            return false;
        }
        if (filters.dateTo.length && filters.dateTo < log.date.substring(0, filters.dateTo.length)) {
            return false;
        }
        if (filters.myQth && filters.myQth.length && filters.myQth !== log.myQth) {
            return false;
        }
        if (filters.sp.length && filters.sp.toLowerCase() !== log.sp.toLowerCase()) {
            return false;
        }
        if (filters.itu.length && filters.itu.toLowerCase() !== log.itu.toLowerCase().substring(0, filters.itu.length)) {
            return false;
        }
        if (filters.cont.length && filters.cont.toLowerCase() !== log.continent.toLowerCase().substring(0, filters.cont.length)) {
            switch(filters.cont) {
                case 'AF':
                    return ($.inArray(log.itu, ['ITU Geneva', 'United Nations']) !== -1 ? true : false);
                case 'AS':
                    return ($.inArray(log.itu, ['Cyprus SBA', 'ITU Geneva', 'United Nations']) !== -1 ? true : false);
                case 'EU':
                    return ($.inArray(log.itu, ['ITU Geneva', 'SMO Malta', 'United Nations']) !== -1 ? true : false);
                case 'OC':
                case 'NA':
                case 'SA':
                    return ($.inArray(log.itu, ['ITU Geneva', 'United Nations']) !== -1 ? true : false);
            }
            return false;
        }
        if (filters.gsq.length && filters.gsq.toLowerCase() !== log.gsq.toLowerCase().substring(0, filters.gsq.length)) {
            return false;
        }
        return true;
    },

    load: (callsign) => {
        frm.start = Date.now();
        $.ajax({
            type: 'GET',
            url: '/logs/' + callsign.replace('/','-') + '/logs',
            dataType: 'json',
            success: function (data) {
                logs = data.logs;
                $(logs).each(function(idx, log) {
                    logs[idx].datetime = log.date + ' ' + log.time;
                    logs[idx].countyName = (log.county.indexOf(',') > 0 ? log.county.split(',')[1] : '');
                    logs[idx].itusp = log.itu + log.sp;
                    logs[idx].ituband = log.itu + log.band;
                    logs[idx].callband = log.call + log.band;
                });
                frm.getFilters();
                $('table.list tbody').html(frm.parseLogs());
                frm.compact();
                frm.count();
                $('#logUpdated').text(data.lastPulled);
                frm.info();
                frm.getGridSquares();

                frm.addLinks();
                $("body").removeClass("loading");
                console.log('Updated in ' + ((Date.now() - frm.start)/1000) + ' seconds');
            }
        })
    },

    parseLogs: () => {
        let html = [];
        let sortField = $('select[name=sortField]').val();
        let showAll = filters.showAll;
        console.log('Parselogs - showAll is ' + (showAll ? 'true' : 'false'));

        switch(sortField) {
            case 'county':
                sortField = 'countyName'
            case 'date':
                sortField = 'datetime';
                break;
            case 'itu':
                sortField = 'itusp';
                break;
            case 'qsoBands':
                sortField = 'bandCount';
                break;
        }
        let sortZa = $('input[name=sortZA]').prop('checked') ? false : true;
        if (sortField) {
            switch (sortField) {
                case 'qsoCount':
                case 'km':
                    sortZa = !sortZa;
                    break;
            }
            frm.sortLogs(sortField, sortZa);
        }
        logsFiltered = [];
        $.each(logs, function(idx, log) {
            if (frm.isVisible(log)){
                logsFiltered.push(log)
            }
        });
        $.each(logsFiltered, function(idx, log){
            let bonus = false;
            switch (log.itu) {
                case 'Cyprus SBA':
                case 'ITU Geneva':
                case 'SMO Malta':
                case 'United Nations':
                    bonus = true;
                    break;
            }
            if (!showAll && (idx > filters.showMax)) {
                return false;
            }
            let qsos = frm.getQsos(log.qsos);
            html.push(
                '<tr' + (bonus ? " class='bonus' title='Bonus Entity for some QRZ Awards'" : "") + ">" +
                '<td class="r">' + (log.logNum)+ '</td>' +
                '<td class="r">' +
                    (log.conf_qc === '1' ?
                        "<div class='conf_q' title='Confirmed in QRZ'></div>"
                        : (log.conf_qc === '2' ? "<div class='conf_c' title='Confirmed in Clublog'></div>" : '')
                    ) +
                '</td>' +
                '<td class="not-compact multi-qth">' + log.myGsq + '</td>' +
                '<td data-link="myQth" class="not-compact multi-qth nowrap">' + log.myQth + '</td>' +
                '<td class="nowrap">' + log.date + '</td>' +
                '<td class="nowrap">' + log.time + '</td>' +
                '<td data-link="call">' + log.call + '</td>' +
                '<td class="nowrap" title="' + qsos.text + '" style="cursor: pointer">' + qsos.count + '</td>' +
                '<td class="not-compact nowrap"><b>' + log.bandCount + '</b> ' + qsos.html + '</td>' +
                '<td class="not-compact">' + log.name + '</td>' +
                '<td data-link="mode"><span class="mode m' + log.mode + '">' + log.mode + '</span></td>' +
                '<td data-link="band"><span class="band band' + log.band + '">' + log.band + '</span></td>' +
                '<td class="r">' + log.rx + '</td>' +
                '<td class="r">' + log.tx + '</td>' +
                '<td class="r">' + log.pwr + '</td>' +
                '<td>' + log.qth + '</td>' +
                '<td class="not-compact">' + log.countyName + '</td>' +
                '<td data-link="sp">' + log.sp + '</td>' +
                '<td class="nowrap" data-link="itu">' + (log.flag ?  '<span class="fi fi-' + log.flag +'"></span>' : '') + log.itu + '</td>' +
                '<td data-link="cont">' + log.continent + '</td>' +
                '<td data-link="gsq">' + log.gsq + '</td>' +
                '<td class="r">' + (typeof log.km === 'number' ? log.km : '') + '</td>' +
                '<td class="r not-compact">' + (typeof log.deg === 'number' ? log.deg : '') + '</td>' +
                '<td class="r not-compact">' + log.comment + '</td>'
            )
        });
        return html.join('\n');
    },

    setActions: () =>{
        ('Y' === COOKIE.get('compact') ?
            $('input#compact_Y').prop('checked','checked') :
            $('input#compact_N').prop('checked','checked')
        );
        if (typeof q.band === 'string') {
            let bands = q.band.split(',');
            $('input[name=band]').prop('checked', false);
            for (let i= 0; i < bands.length; i++) {
                $('input[data-band="' + bands[i]  + '"]').prop('checked', 'checked');
            }
            $('.bandsAll').prop('checked', $('input[name=band]:not(:checked)').length ? false : 'checked');
        }
        if (typeof q.dateFrom === 'string') {
            $('input[name=dateFrom]').val(q.dateFrom);
        }
        if (typeof q.dateTo === 'string') {
            $('input[name=dateTo]').val(q.dateTo);
        }
        if (typeof q.call === 'string') {
            $('input[name=call]').val(q.call);
        }
        if (typeof q.sp === 'string') {
            $('input[name=sp]').val(q.sp);
        }
        if (typeof q.itu === 'string') {
            $('input[name=sp]').val(q.sp);
        }
        if (typeof q.cont === 'string') {
            $('select[name=cont]').val(q.cont);
        }
        if (typeof q.gsq === 'string') {
            $('input[name=gsq]').val(q.gsq);
        }
        if (typeof q.myQth === 'string') {
            $('select[name=myQth]').val(q.myQth);
        }
        $('input[name=band]').click(function(e) {
            if (e.shiftKey) {
                $('input[name=band]').prop('checked', false);
                $(this).prop('checked', 'checked');
            }
            let all = $('input[name=band]:not(:checked)').length === 0;
            $('.bandsAll').prop('checked', (all ? 'checked' : false));
            $(this).blur();
            frm.update();
        });
        $('.bandsAll').click(function() {
            $('input[name=band]').prop('checked', $(this).prop('checked'));
            $('input[name=band]').trigger('change');
            $(this).blur();
            frm.update();
        });
        $('input[name=mode]').click(function(e) {
            if (e.shiftKey) {
                $('input[name=mode]').prop('checked', false);
                $(this).prop('checked', 'checked');
            }
            let all = $('input[name=mode]:not(:checked)').length === 0;
            $('.modesAll').prop('checked', (all ? 'checked' : false));
            $(this).blur();
            frm.update();
        });
        $('.modesAll').click(function() {
            $('input[name=mode]').prop('checked', $(this).prop('checked'));
            $('input[name=mode]').trigger('change');
            $(this).blur();
            frm.update();
        });
        $('input[name=conf]').change(function() {
            $(this).blur();
            frm.update();
        });
        $('input[name=compact]').change(function() {
            $(this).blur();
            COOKIE.set('compact', $(this).val(), '/');
            frm.compact();
            LMap.fitToBox();
        })
        $('input[name=dateFrom]').change(function() {
            frm.update();
        });
        $('input[name=dateTo]').change(function() {
            frm.update();
        });
        $('input[name=call]').keyup(function() {
            frm.update();
        });
        $('input[name=sp]').keyup(function() {
            frm.update();
        });
        $('input[name=itu]').keyup(function() {
            frm.update();
        });
        $('select[name=cont]').change(function() {
            $(this).blur();
            frm.update();
        });
        $('input[name=gsq]').keyup(function() {
            frm.update();
        });
        $('select[name=myQth]').change(function() {
            $(this).blur();
            frm.update();
            LMap.drawQTH();
        });
        $('select[name=sortField]').change(function() {
            frm.update();
            $(this).blur();
        });
        $('input[name=sortZA]').change(function() {
            frm.update();
            $(this).blur();
        });
        $('button#reload').click(function() {
            window.location.reload();
        });
        $('button#reset').click(function() {
            $('input[name=band]').prop('checked','checked');
            $('input.bandsAll').prop('checked','checked');
            $('input[name=mode]').prop('checked','checked');
            $('input#conf_All').prop('checked','checked');
            $('input[name=dateFrom]').val(logDates[0]);
            $('input[name=dateTo]').val(logDates[1]);
            $('input[name=call]').val('');
            $('input[name=sp]').val('');
            $('input[name=itu]').val('');
            $('select[name=cont]').val('');
            $('input[name=gsq]').val('');
            $('select[name=myQth]').val('');
            $('select[name=sortField]').val('logNum');
            $('input[name=sortZA]').prop('checked', 'checked');
            $('.sortable').removeClass('asc').removeClass('desc');
            $('input#compact_N').prop('checked','checked');
            $('th[data-field=logNum]').addClass('desc');
            frm.update();
            $(this).blur();
        });
        $('#show_list, #show_map, #show_stats').click(function() {
            let id = $(this).attr('id');
            $(['show_list','show_map', 'show_stats']).each((idx, val) => {
                switch(val) {
                    case 'show_map':
                        LMap.drawGridSquares();
                        break;
                    case 'show_stats':
                        frm.getStats();
                        break;
                }
                if (val === id) {
                    $('#' + val).removeClass('is-inactive').addClass('is-active');
                    $('.' + val.substring(5)).show();
                } else {
                    $('#' + val).removeClass('is-active').addClass('is-inactive');
                    $('.' + val.substring(5)).hide();
                }
            });
        });
        var $sortable = $('.sortable');
        $sortable.on('click', function(){
            var $this = $(this);
            var asc = $this.hasClass('asc');
            var desc = $this.hasClass('desc');
            $sortable.removeClass('asc').removeClass('desc');

            if (desc || (!asc && !desc)) {
                $this.addClass('asc');
                $('input[name=sortZA]').prop('checked', false);
            } else {
                $this.addClass('desc');
                $('input[name=sortZA]').prop('checked', 'checked');
            }
            $('select[name=sortField]').val($this.data('field'));
            frm.update();
        });
        $('.quicklinks a').on('click', function() {
            if ($.attr(this, 'href') !== '#') {
                $('html, body').animate({
                    scrollTop: $($.attr(this, 'href')).offset().top
                }, 500);
            }
            return false;
        });
        $('input[name="showAll"]').on('change', function(e) {
            filters.showAll = $(this).is(':checked');
            frm.update();
            // $('table.list tbody').html(frm.parseLogs());
            // frm.addLinks();
            // frm.count();
        });
    },
    setVal: (source, value)=>  {
        switch(source) {
            case 'band':
                $('input[name=' + source + ']').prop('checked', false);
                $('input[data-band=' + value + ']').prop('checked', true);
                break;
            case 'mode':
                $('input[name=' + source + ']').prop('checked', false);
                $('input[data-mode=' + value + ']').prop('checked', true);
                break;
            case 'myQth':
                $('select[name=' + source + ']').val(value);
                break;
            default:
                $('input[name=' + source + ']').val(value);
                break;
        }
        frm.update();
        return false;
    },

    sortLogs: (sortField, sortZa) => {
        if (sortZa) {
            logs.sort(function(a,b){
                let aVal = (typeof a[sortField] === 'string' ? a[sortField].toLowerCase() || '|||' : a[sortField]);
                let bVal = (typeof b[sortField] === 'string' ? b[sortField].toLowerCase() || '|||' : b[sortField]);
                return ((aVal < bVal) ? -1 : ((aVal > bVal) ? 1 : 0));
            });
        } else {
            logs.sort(function(a,b){
                let aVal = (typeof a[sortField] === 'string' ? a[sortField].toLowerCase() || '!!!' : a[sortField]);
                let bVal = (typeof b[sortField] === 'string' ? b[sortField].toLowerCase() || '!!!' : b[sortField]);
                return ((bVal < aVal) ? -1 : ((bVal > aVal) ? 1 : 0));
            });
        }
    },


    info: () => {
        let sp = frm.getUniqueValues('sp');
        let cont = frm.getUniqueValues('continent');
        $('#statsQsos').text(logsFiltered.length);
        $('#statsCalls').text(frm.getUniqueValues('call').count);
        $('#statsCounties').html(frm.getUniqueValuesStats('county'));
        $('#statsSps').html(frm.getUniqueValuesStats('sp'));
        $('#statsItus').html(frm.getUniqueValuesStats('itu'));
        $('#statsContinents').html(frm.getUniqueValuesStats('continent'));
        $('#statsGsqs').text(frm.getUniqueValues('gsq').count);
        $('#statsBands').html(frm.getUniqueValuesStats('band'));
        $('#statsItuBands').text(frm.getUniqueValues('ituband').count);
        $('#statsCallBands').text(frm.getUniqueValues('callband').count);
    },

    update: () => {
        frm.start = Date.now();
        $('body').addClass('loading');
        window.setTimeout(function() { frm.update_doit()}, 1);
    },

    update_doit: () => {
        let gsq, gsqList, i;
        frm.getFilters();
        $('table.list tbody').html(frm.parseLogs());
        frm.compact();
        frm.count();
        frm.info();
        frm.getGridSquares();
        frm.addLinks();
        if ($('.map').is(':visible')) {
            LMap.drawGridSquares();
            $('#layer_squares').prop('checked', 'checked')
            google.maps.event.trigger(LMap.map, 'zoom_changed');
            if (LMap.infoWindowGsq) {
                gsq = LMap.infoWindowGsq;
                gsqList = frm.getUniqueValues('gsq').values;
                if ($.inArray(LMap.infoWindowGsq, gsqList) !== -1) {
                    for (i=0; i<gsqs.length; i++) {
                        if (gsqs[i].gsq === gsq) {
                            LMap.gsqInfoWindowOpen(gsqs[i]);
                            break;
                        }
                    }
                } else {
                    LMap.gsqInfoWindowClose();
                }
            }
        }
        $("body").removeClass("loading");
        console.log('Updated in ' + ((Date.now() - frm.start)/1000) + ' seconds');
    },
}

// https://github.com/szepeshazi/print-elements - András Szepesházi
var PrintElements = (function () {
    "use strict";

    var hideFromPrintClass = "pe-no-print";
    var preservePrintClass = "pe-preserve-print";
    var preserveAncestorClass = "pe-preserve-ancestor";
    var bodyElementName = "BODY";

    var _hide = function (element) {
        if (!element.classList.contains(preservePrintClass)) {
            element.classList.add(hideFromPrintClass);
        }
    };

    var _preserve = function (element, isStartingElement) {
        element.classList.remove(hideFromPrintClass);
        element.classList.add(preservePrintClass);
        if (!isStartingElement) {
            element.classList.add(preserveAncestorClass);
        }
    };

    var _clean = function (element) {
        element.classList.remove(hideFromPrintClass);
        element.classList.remove(preservePrintClass);
        element.classList.remove(preserveAncestorClass);
    };

    var _walkSiblings = function (element, callback) {
        var sibling = element.previousElementSibling;
        while (sibling) {
            callback(sibling);
            sibling = sibling.previousElementSibling;
        }
        sibling = element.nextElementSibling;
        while (sibling) {
            callback(sibling);
            sibling = sibling.nextElementSibling;
        }
    };

    var _attachPrintClasses = function (element, isStartingElement) {
        _preserve(element, isStartingElement);
        _walkSiblings(element, _hide);
    };

    var _cleanup = function (element, isStartingElement) {
        _clean(element);
        _walkSiblings(element, _clean);
    };

    var _walkTree = function (element, callback) {
        var currentElement = element;
        callback(currentElement, true);
        currentElement = currentElement.parentElement;
        while (currentElement && currentElement.nodeName !== bodyElementName) {
            callback(currentElement, false);
            currentElement = currentElement.parentElement;
        }
    };

    var _print = function (elements) {
        for (var i = 0; i < elements.length; i++) {
            _walkTree(elements[i], _attachPrintClasses);
        }
        window.print();
        for (i = 0; i < elements.length; i++) {
            _walkTree(elements[i], _cleanup);
        }
    };

    return {
        print: _print
    };
})();

frm._init();
