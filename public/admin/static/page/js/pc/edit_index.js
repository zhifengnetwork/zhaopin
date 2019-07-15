/*删除元素=>函数*/
function removerEle(_this){
	console.log(_this);
	$(_this).parent().parent().remove();
}
$(function(){
	/*根据固定的class=>鼠标悬浮=>遮罩层=>删除*/
	$('.cursor_wrap').hover(function(){
		console.log('悬浮');
		$(this).find('.cursor_box').css('height',$(this).height());
		$(this).find('.cursor_box').show();
		return false;
	},function(){
		console.log('离开');
		$(this).find('.cursor_box').hide();
		$(this).find('.cursor_box').css('height','auto');
		return false;
	})
	
	/*轮播图*/
	$('.rotationChart').on('click', function() {
		console.log('轮播图！');
		var eleInd = $('.sowingMapWrap').index();
		console.log(eleInd);
		var roStr = '';
		if(eleInd != -1) {
		/*<!--publi 轮播图 wrap-->*/
		roStr += '<div class="sowingMapWrap chart_wrap' + eleInd + '">';
		} else {
		roStr += '<div class="sowingMapWrap chart_wrap">';
		}
		if(eleInd != -1) {
			/*<!--swiper的外框-->*/
			roStr += '<div class="swiper-container swiper_' + eleInd + '">';
		} else {
			roStr += '<div class="swiper-container swiper_start">';
		}
				roStr += '<div class="swiper-wrapper clearfloat">';
					/*<!--（循环）轮播-项-->*/
					roStr += '<div class="swiper-slide">';
						/*<!--固定widen:690px;height:200px;-->*/
						roStr += '<img class="sowingMapImgNQ" src="img/0005.png" alt="" />';
					roStr += '</div>';
					roStr += '<div class="swiper-slide">';
						roStr += '<img class="sowingMapImgNQ" src="img/0005.png" alt="" />';
					roStr += '</div>';
					roStr += '<div class="swiper-slide">';
						roStr += '<img class="sowingMapImgNQ" src="img/0005.png" alt="" />';
					roStr += '</div>';
				roStr += '</div>';
				/*<!-- Add Pagination -->*/
				roStr += '<div class="swiper-pagination"></div>';
			roStr += '</div>';
		roStr += '</div>';
		console.log(roStr);
		/*追加元素*/
		$('.wrap_new').append(roStr);

		/*对应的script*/
		var script = document.createElement('script');
		//						script.type = 'text/jacascript';
		//						script.src = 'url';     //填自己的js路径

		var roScript = '';
		if(eleInd != -1) {
			roScript += "var swiper = new Swiper('.swiper_" + eleInd + "', {";
		} else {
			roScript += "var swiper = new Swiper('.swiper_start', {";
		}
		/*方向*/
		roScript += "direction: 'horizontal',";
		/*轮播项-循环*/
		roScript += 'loop: true,';
		//设置自动循环播放
		roScript += 'autoplay: {';
		/*时间间隔*/
		roScript += 'delay: 3000,';
		/*允许客户操作后，自动轮播*/
		roScript += 'disableOnInteraction: false,';
		roScript += '},';
		/*分页器*/
		roScript += 'pagination: {';
		roScript += "el: '.swiper-pagination',";
		roScript += '},';
		roScript += '});';
		script.innerHTML = roScript;
		console.log(eleInd);
		if(eleInd != -1) {
			$('.sowingMapWrap').eq(eleInd + 1).append(script);
		} else {
			$('.sowingMapWrap').eq(0).append(script);
		}
	})


	/*<< 删*/
//	var swiper = new Swiper('.swiper_start', {
//		/*方向*/
//		direction: 'horizontal',
//		/*轮播项-循环*/
//		loop: true,
//		//设置自动循环播放
//		autoplay: {
//			/*时间间隔*/
//			delay: 3000,
//			/*允许客户操作后，自动轮播*/
//			disableOnInteraction: false,
//		},
//		/*分页器*/
//		pagination: {
//			el: '.swiper-pagination',
//		},
//	})
	/* 删 >>*/
})
