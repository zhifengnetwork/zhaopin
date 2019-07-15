//dom加载完成后执行的js
;$(function(){

    var alert_speed = 1500;

	//全选的实现
	$(".check-all").click(function(){
		$(".ids").prop("checked", this.checked);
	});
	$(".ids").click(function(){
		var option = $(".ids");
		option.each(function(i){
			if(!this.checked){
				$(".check-all").prop("checked", false);
				return false;
			}else{
				$(".check-all").prop("checked", true);
			}
		});
	});

    //ajax get请求
    $('.ajax-get').click(function(){
        var target;
        var that = this;
        if ( $(this).hasClass('confirm') ) {
            var confirm_text = $(this).data('confirm') ? $(this).data('confirm') : '确认要执行该操作吗?';
            if(!confirm(confirm_text)){
                return false;
            }
        }
        if ( (target = $(this).attr('href')) || (target = $(this).attr('url')) ) {
            $.get(target).success(function(data){
                if (data.code==1) {
                    if (data.url) {
                        updateAlert(data.msg + ' 页面即将自动跳转~','alert-success');
                    }else{
                        updateAlert(data.msg,'alert-success');
                    }
                    setTimeout(function(){
                        if (data.url) {
                            location.href=data.url;
                        }else if( $(that).hasClass('no-refresh')){
                            $('#top-alert').find('button').click();
                        }else{
                            location.reload();
                        }
                    },alert_speed);
                }else{
                    updateAlert(data.msg);
                    setTimeout(function(){
                        if (data.url) {
                            location.href=data.url;
                        }else{
                            $('#top-alert').find('button').click();
                        }
                    },alert_speed);
                }
            });

        }
        return false;
    });

    //ajax post submit请求
    $('.ajax-post').click(function(){
        var target,query,form;
        var target_form = $(this).attr('target-form');
        var that = this;
        var nead_confirm=false;
        if( ($(this).attr('type')=='submit') || (target = $(this).attr('href')) || (target = $(this).attr('url')) ){
            form = $('.'+target_form);

            if ($(this).attr('hide-data') === 'true'){//无数据时也可以使用的功能
            	form = $('.hide-data');
            	query = form.serialize();
            }else if (form.get(0)==undefined){
            	return false;
            }else if ( form.get(0).nodeName=='FORM' ){
                if ( $(this).hasClass('confirm') ) {
                    var confirm_text = $(this).data('confirm') ? $(this).data('confirm') : '确认要执行该操作吗?';
                    if(!confirm(confirm_text)){
                        return false;
                    }
                }
                
                if($(this).attr('url') !== undefined){
                	target = $(this).attr('url');
                }else{
                	target = form.get(0).action;
                }

                var files = form.find('input[type=file]');
                if (files.length > 0) {
                    if (typeof FormData === undefined) {
                        updateAlert('暂不支持AJAX表单文件上传' ,'alert-success');
                        return false;
                    }
                    var query = new FormData();
                    $.each(form.find('input[type=file]'), function(i, tag) {
                        $.each($(tag)[0].files, function(i, file) {
                            query.append(tag.name, file);
                        });
                    });
                    var params = form.serializeArray();
                    $.each(params, function (i, val) {
                        query.append(val.name, val.value);
                    });

                    $(that).addClass('disabled').attr('autocomplete','off').prop('disabled',true);
                    // 待整合到下面的的$.post中
                    $.ajax({
                        url: target,
                        data: query,
                        processData: false,
                        contentType: false,
                        type: 'POST',
                        success: function(data){
                            if (data.code==1) {
                                if (data.url) {
                                    updateAlert(data.msg + ' 页面即将自动跳转~','alert-success');
                                }else{
                                    updateAlert(data.msg ,'alert-success');
                                }
                                setTimeout(function(){
                                    $(that).removeClass('disabled').prop('disabled',false);
                                    if (data.url) {
                                        location.href=data.url;
                                    }else if( $(that).hasClass('no-refresh')){
                                        $('#top-alert').find('button').click();
                                    }else{
                                        location.reload();
                                    }
                                },alert_speed);
                            }else{
                                updateAlert(data.msg ,'alert-danger');
                                setTimeout(function(){
                                    $(that).removeClass('disabled').prop('disabled',false);
                                    if (data.url) {
                                        location.href=data.url;
                                    }else{
                                        $('#top-alert').find('button').click();
                                    }
                                },alert_speed);
                            }
                        }
                    });
                    return false;
                } else {
                    query = form.serialize();
                }
            }else if( form.get(0).nodeName=='INPUT' || form.get(0).nodeName=='SELECT' || form.get(0).nodeName=='TEXTAREA') {
                form.each(function(k,v){
                    if(v.type=='checkbox' && v.checked==true){
                        nead_confirm = true;
                    }
                })
                if ( nead_confirm && $(this).hasClass('confirm') ) {
                    var confirm_text = $(this).data('confirm') ? $(this).data('confirm') : '确认要执行该操作吗?';
                    if(!confirm(confirm_text)){
                        return false;
                    }
                }
                query = form.serialize();
            }else{
                if ( $(this).hasClass('confirm') ) {
                    var confirm_text = $(this).data('confirm') ? $(this).data('confirm') : '确认要执行该操作吗?';
                    if(!confirm(confirm_text)){
                        return false;
                    }
                }
                query = form.find('input,select,textarea').serialize();
            }
            $(that).addClass('disabled').attr('autocomplete','off').prop('disabled',true);
            $.post(target,query).success(function(data){
                if (data.code==1) {
                    if (data.url) {
                        updateAlert(data.msg + ' 页面即将自动跳转~','alert-success');
                    }else{
                        updateAlert(data.msg ,'alert-success');
                    }
                    setTimeout(function(){
                    	$(that).removeClass('disabled').prop('disabled',false);
                        if (data.url) {
                            if ( $(that).hasClass('layer-close') ) {
                                parent.location.reload();
                            }
                            location.href=data.url;
                        }else if( $(that).hasClass('no-refresh')){
                            $('#top-alert').find('button').click();
                        }else{
                            if ( $(that).hasClass('layer-close') ) {
                                parent.location.reload();
                            }else{
                                location.reload();
                            }
                        }
                    },alert_speed);
                }else{
                    updateAlert(data.msg ,'alert-danger');
                    setTimeout(function(){
                    	$(that).removeClass('disabled').prop('disabled',false);
                        if (data.url) {
                            location.href=data.url;
                        }else{
                            $('#top-alert').find('button').click();
                        }
                    },alert_speed);
                }
            });
        }
        return false;
    });
    
    
    /**顶部警告栏*/
	var content = $('#main');
	var top_alert = $('#top-alert');
	top_alert.find('.close').on('click', function () {
		top_alert.removeClass('block').slideUp(200);
		// content.animate({paddingTop:'-=55'},200);
	});
    var alert_speed = 1500;
    window.updateAlert = function (text,c,callback) {
		text = text||'default';
		c = c||false;
		if ( text!='default' ) {
            top_alert.find('.alert-content').text(text);
			if (top_alert.hasClass('block')) {
			} else {
				top_alert.addClass('block').slideDown(200);
				// content.animate({paddingTop:'+=55'},200);
			}
		} else {
			if (top_alert.hasClass('block')) {
				top_alert.removeClass('block').slideUp(200);
				// content.animate({paddingTop:'-=55'},200);
			}
		}
		if ( c!=false ) {
            top_alert.removeClass('alert-danger alert-warn alert-info alert-success').addClass(c);
		}
		setTimeout(function(){
			$('#top-alert').find('button').click();
			callback && callback();
		}, alert_speed+500);
	};  
});

//导航高亮
function highlight_subnav(url){
	var $subnav = $("#subnav");
    $subnav.find('a[href="'+url+'"]').parent().addClass("active");
	$subnav.find("a[href='" + url + "']").parent().parents('li').addClass("active");
	$subnav.find("a[href='" + url + "']").parent().parents('li').addClass("open");
}