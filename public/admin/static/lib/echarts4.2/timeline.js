/*时间轴加类目轴，数据缩放，实线虚线结合，点与坐标不对应*/
/*
 * name 图表节点描述名称
 * date_arr X轴时间 ['2017-03', '2017-04', '2017-05', '2017-06', '2017-07']
 * data_list 数据 [{
				  	date: '2017-03-01',
				    data: 900000
				  }]
 * min_date 最小日期 具体到天,影响折线图分布范围,例如:2017-03-01
 * max_date 最大日期
 * */
function echart_timeline_option(name,date_arr,data_list,min_date,max_date){

	let cost = [];

	for (let i = 0; i < data_list.length; i++) {
	    cost.push({
	        name: data_list[i].date ,
	        value: [data_list[i].date, data_list[i].data]
	    });
	}

	let costChange = {
	    changeDate: date_arr,
	    cost: cost,
	    minDate: min_date,
	    maxDate: max_date,
	    dashLastStart: 2
	};

	let monthCount = costChange.changeDate.length;
	let data = costChange.cost;
	let chartData = {
	    xAxisNames: costChange.changeDate,
	    seriesData: data,
	    axisLabelFormatter: '{value}',
	    name: name,
	    seriesDash: false,
	    dashStart: data.length - costChange.dashLastStart,
	    minDate: costChange.minDate,
	    maxDate: costChange.maxDate,
	    scrollLen: Math.ceil(100 - 12 / this.monthCount * 100),
	    showScroll: this.monthCount > 12,
	    bottom: this.monthCount > 12 ? 50 : 0
	};

	let seriesData = [];
	
	if (chartData.seriesDash) {
	    let len = chartData.seriesData.length;
	    let minusArr = [];
	    for (let i = 0; i < len; i++) {
	        minusArr.push({
	            name: '-',
	            value: []
	        });
	    }
	    seriesData = [{
	            name: chartData.name,
	            symbol: 'circle',
	            symbolSize: 12,
	            type: 'line',
	            smooth: false,
	            data: (chartData.seriesData.slice(0, chartData.dashStart)).concat(minusArr.slice(0, len - chartData.dashStart))
	        },
	        {
	            name: chartData.name,
	            symbol: 'emptyCircle',
	            symbolSize: 6,
	            type: 'line',
	            smooth: false,
	            data: (chartData.seriesData.slice(0, chartData.dashStart)).concat(minusArr.slice(0, len - chartData.dashStart))
	        },
	        {
	            name: chartData.name,
	            symbol: 'circle',
	            symbolSize: 12,
	            type: 'line',
	            smooth: false,
	            itemStyle: {
	                normal: {
	                    color: '#95EBE1',
	                    lineStyle: {
	                        width: 2,
	                        type: 'dashed'
	                    }
	                }
	            },
	            data: (minusArr.slice(0, chartData.dashStart - 1)).concat(chartData.seriesData.slice(chartData.dashStart - 1, len))
	        },
	        {
	            name: chartData.name,
	            symbol: 'circle',
	            symbolSize: 6,
	            type: 'line',
	            smooth: false,
	            itemStyle: {
	                normal: {
	                    lineStyle: {
	                        width: 2,
	                        type: 'dotted'
	                    }
	                }
	            },
	            data: (minusArr.slice(0, chartData.dashStart - 1)).concat(chartData.seriesData.slice(chartData.dashStart - 1, len))
	        },
	        {
	            name: chartData.name,
	            symbol: 'circle',
	            symbolSize: 12,
	            type: 'line',
	            smooth: false,
	            data: (minusArr.slice(0, chartData.dashStart - 1)).concat(chartData.seriesData.slice(chartData.dashStart - 1, chartData.dashStart)).concat(minusArr.slice(0, len - chartData.dashStart))
	        },
	        {
	            name: chartData.name,
	            symbol: 'emptyCircle',
	            symbolSize: 6,
	            type: 'line',
	            smooth: false,
	            data: (minusArr.slice(0, chartData.dashStart - 1)).concat(chartData.seriesData.slice(chartData.dashStart - 1, chartData.dashStart)).concat(minusArr.slice(0, len - chartData.dashStart))
	        }
	    ];
	} else {
	    seriesData = [{
	            name: chartData.name,
	           // symbol: 'circle',
	           // symbolSize: 12,
	            type: 'line',
	            smooth: true,
	            data: chartData.seriesData
	        }
	    ];
	}

	let xAxisNames1 = [];
	for (let i = 0; i < chartData.seriesData.length; i++) {
	    xAxisNames1.push('-');
	}
	option = {
	    color: '#28BBAB',
	    backgroundColor: '#404a59',
	    tooltip: {
	        backgroundColor: 'rgba(46, 126, 139, 0.90)',
	        padding: [10, 20, 10, 8],
	        textStyle: {
	            fontSize: 12,
	            lineHeight: 24
	        },
	        trigger: 'axis',
	        axisPointer: {
	            type: 'line',
	            lineStyle: {
	                type: 'dashed',
	                color: '#28BBAB'
	            }
	        },
	        formatter: function(params, ticket, callback) {
	            callback;
	            var htmlStr = '';
	            var valMap = {};
	            for (var i = 0; i < params.length; i++) {
	                var param = params[i];
	                var xName = param.name;
	                var seriesName = param.seriesName;
	                var value = param.value;
	                
	                if (value.length === 0) {
	                    continue;
	                }
	                if (valMap[seriesName] && valMap[seriesName][0] === value[0] && valMap[seriesName][1] === value[1]) {
	                    continue;
	                }
	                htmlStr += xName;
	               
	                htmlStr += '<br/><div>';
	                htmlStr += seriesName + '：' + value[1];
	                htmlStr += '</div>';
	                valMap[seriesName] = value;
	            }
	            return htmlStr;
	        }
	    },
	    grid: {
	        containLabel: true
	    },
	    dataZoom: [{
	        type: 'slider',
	        zoomLock: true,
	        show: true,
	        realtime: true,
	        start: 50,
	        filterMode: 'none',
	        end: 100,
	        dataBackground: {
	            lineStyle: {
	                opacity: 0
	            },
	            areaStyle: {
	                opacity: 0
	            }
	        },
	        labelFormatter: function(value) {
	            let year = (new Date(value)).getFullYear();
	            let month = (new Date(value)).getMonth() + 1;
	            if (month < 10) {
	                month = '0' + month;
	            }
	            return year + '-' + month;
	        },
	        handleIcon: 'M10.7,11.9v-1.3H9.3v1.3c-4.9,0.3-8.8,4.4-8.8,9.4c0,5,3.9,9.1,8.8,9.4v1.3h1.3v-1.3c4.9-0.3,8.8-4.4,8.8-9.4C19.5,16.3,15.6,12.2,10.7,11.9z M13.3,24.4H6.7V23h6.6V24.4z M13.3,19.6H6.7v-1.4h6.6V19.6z',
	        handleSize: '80%',
	        handleStyle: {
	            color: '#fff',
	            shadowBlur: 3,
	            shadowColor: 'rgba(0, 0, 0, 0.6)',
	            shadowOffsetX: 2,
	            shadowOffsetY: 2
	        },
	        xAxisIndex: [0, 1]
	    }],
	    xAxis: [{
	        type: 'time',
	        min: chartData.minDate,
	        max: chartData.maxDate,
	        data: xAxisNames1,
	        splitLine: {
	            show: false
	        },
	        axisLine: {
	            show: false
	        },
	        axisLabel: {
	            show: false
	        },
	        axisTick: {
	            show: false
	        },
	        axisPointer: {
	            triggerTooltip: true
	        }
	    }, {
	        type: 'category',
	        position: 'bottom',
	        data: chartData.xAxisNames,
	        axisPointer: {
	            triggerTooltip: false,
	            show: false
	        },
	        axisLabel: {
	            color: '#ADB9C7'
	        },
	        axisLine: {
	            lineStyle: {
	                color: '#ddd'
	            }
	        },
	        axisTick: {
	            show: false
	        }
	    }],
	    yAxis: {
	        type: 'value',
	        axisLine: {
	            show: false,
	            lineStyle: {
	                color: '#999'
	            }
	        },
	        axisTick: {
	            show: false
	        },
	        splitLine: {
	            lineStyle: {
	                type: 'dash',
	                color: '#999'
	            }
	        },
	        axisLabel: {
	            color: '#ADB9C7',
	            formatter: function(value) {
	                if (chartData.axisLabelFormatter === '{value}w') {
	                    return (parseInt(value, 10) / 10000) + 'w';
	                } else {
	                    return value;
	                }
	            }
	        }
	    },
	    series: seriesData
	};
	return option;
}