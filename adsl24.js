/* Copyright (C) 2012 Kenny Millington
 *
 * This file is part of adsl24meter.
 * 
 * adsl24meter is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *  
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
var ADSL24Client = {
    ajaxUrl : undefined,

    fetchAndRender : function() {
        $.get(ADSL24Client.ajaxUrl, {
            'action' : 'usage',
        }, function(resp) {
            var options = {
                enableInteractivity: true,
                isStacked: true,
                chartArea: { left: 0, width: '100%' },
                hAxis: {
                    gridlines: { color: '#FFF', count: resp['wd_total'] },
                    viewWindow: { min: 0, max: resp['bw_total'] }
                },
                backgroundColor: '#222'
            };
            
            if(resp['bw_credit'] >= 0) {
                options['series'] = [ { color: '#2EFF29' }, { color: '#333' } ];
                
                var data = google.visualization.arrayToDataTable([
                    ['Item', 'Used', 'Credit'],
                    ['Bandwidth', resp['bw_used'], resp['bw_credit']]
                ]);
            } else {
                options['series'] = [ {color: '#2EFF29'}, {color: '#FF2E29'} ];
                var data = google.visualization.arrayToDataTable([
                    ['Item', 'Used', 'Deficit'],
                    ['Bandwidth', resp['bw_scheduled'], -resp['bw_credit']]
                ]);
            }
            
            var chart = $('#bw_chart').data('google_chart');
            
            if(chart == undefined) {
                chart = new google.visualization.BarChart(
                    document.getElementById('bw_chart')
                );

                $('#bw_chart').data('google_chart', chart);
            }

            $('#bw_chart').removeClass('loading');
            $('#bw_report').html(resp['bw_report']);
            
            $('#bw_report')
                .removeClass('good')
                .removeClass('bad')
                .addClass((resp['bw_credit'] >= 0) ? 'good' : 'bad')
                .show();

            chart.draw(data, options);

            setTimeout(ADSL24Client.fetchAndRender, 1000 * 600);
        });
    },

    init : function(ajax_url) {
        this.ajaxUrl = ajax_url;
        google.load('visualization', '1', {packages:['corechart']});
        google.setOnLoadCallback(this.fetchAndRender);

        $(document).ajaxError(function(evt, xhr, settings, thrownError) {
            location.href = location.href;
        });
        
        $(window).bind('beforeunload', function() {
            $(document).unbind('ajaxError');
        });
    }
};
