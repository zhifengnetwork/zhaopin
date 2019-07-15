<?php
require_once 'AopSdk.php';
?>
<style>
.footer {
  background-color: #f4f4f4;
  width: 100%;
  height: 100px;
}
.footer:before,.footer:after{
  clear: both;
}

.foot-link {
  padding-top:40px;
  text-align: center;
}
</style>

<div class="footer">
    <p class="foot-link">
        <span>蚂蚁金服集团</span> | <span>支付宝</span> | <span>招财宝</span>
          | <span>蚂蚁商家中心</span> | <span>芝麻信用</span> | <span>蚂蚁微贷</span> | <span>网商银行</span>
          | <span>开放平台</span> | <span>诚征英才</span> | <span>联系我们</span>
    </p>
</div>
<script>
	// 加载时
	window.onload = function() {
		footSize()
	}
	// 屏幕改变大小
	$(window).resize(function() {
		footSize()
	})
	// 页面点击事件
	$('body').on('click', function() {
		footSize()
	})

	function footSize() {
		var screenHeight = $(window).height()
		var otherHeight = $('.head').height() + $('.content').height()

		var footMarginHeight = 40
		if (screenHeight - 140 > otherHeight) {
			footMarginHeight = screenHeight - otherHeight - 100
		}
		$('.foot').css('margin-top', footMarginHeight)
	}
</script>
