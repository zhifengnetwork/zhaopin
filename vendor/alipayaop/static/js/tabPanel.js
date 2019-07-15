
+function() {
	var Tab = function(el,options){
		this.tplt = '<ul class="nav nav-tabs" role="tablist">'
          +'</ul>'
          +'<div class="tab-content">'
          +'</div>';
        this.options = options;
        this.el = el;
		this.init();
	}

	Tab.prototype.init = function(){
		var data = this.options.data;
		if (!data) {return;};

		var $tabpanel = $(this.tplt);
		
		var li = '<li role="presentation"><a href="" aria-controls="home" role="tab" data-toggle="tab"></a></li>';
		var tab = '<div class="tab-pane" role="tabpanel"></div>'
		var liAry = [],tabAry = [];
		for(var i in data){
			var $li = $(li),$tab = $(tab);
			var component = this.getFormPanel(data[i].apiInParam,'demo/service/'+data[i].apiNameFirstLower+'service.php');
			var html = data[i].apiName+'<br /><span>'+data[i].apiZhName+'</span>'
			$li.find('a').html(html).attr('href','#'+data[i].apiNameFirstLower).end();
			$tab.attr('id',data[i].apiNameFirstLower).append(component)
			
			if(i==0){
				$li.addClass('active');
				$tab.addClass('active');
			}

			liAry.push($li);
			tabAry.push($tab);
		}

		$tabpanel.filter('ul').append(liAry).end()
			.filter('.tab-content').append(tabAry);
		this.component = [$tabpanel];
		$(this.el).append($tabpanel).tab();
	}
	Tab.prototype.getTreePanel = function(data){
		var treePanel = new TreePanel(data);
		return treePanel.getComponent();
	}
	Tab.prototype.getFormPanel = function(data,url){
		var form = new Form(data,this.getTreePanel(data),url);
		return form.getComponent();
	}
	Tab.prototype.getComponent = function(){
		return this.component;
	}

	//js调用控件
	function Plugin(option,relatedTarget) {
	    return this.each(function(){
	        var $this = $(this);
	        var options = $.extend({},$this.data(),typeof option=='object'&&option);
	        var data    = $this.data('bs.tabPanel');
	        if (!data){
	            $this.data('bs.tabPanel', (data = new Tab(this, options)));
	        } 
	    })
	}

	$.fn.tabPanel = Plugin;
	$.fn.tabPanel.constructor = Tab;
}()