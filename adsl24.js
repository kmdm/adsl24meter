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
                    viewWindow: { min: 0, max: resp['bw_total'] },
                    textStyle : { color: '#FFF' }
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

        setInterval(function() {
            $('#duration').each(function() {
                var secs = parseInt($(this).attr('rel')) + 1;
                $(this).attr('rel', secs);
                var mins = Math.floor(secs / 60);
                secs %= 60;

                var mins_t = (mins == 1) ? " minute" : " minutes";
                var secs_t = (secs == 1) ? " second" : " seconds";

                $(this).html(mins + mins_t + ", " + secs + secs_t);

                if(mins >= 10) {
                    $(this).attr('id', 'duration_disabled');
                    ADSL24Client.fetchAndRender();
                }
            });
        }, 1000);
    }
};
