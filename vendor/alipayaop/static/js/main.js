//控制form表单
var Form = function(data,body,url){
  this.tplt = '<form class="form-horizontal">'
            +'<div class="js-form-body">'
            +'</div>'
            +'<div class="form-group">'
            +'  <label class="col-md-2 control-label">执行结果：</label>'
            +'  <div class="col-md-10">'
            +'    <textarea class="form-control js-result" rows="3"></textarea>'
            +'  </div>'
            +'</div>'
            +' <div class="form-group">'
            +'  <div class="col-md-offset-10 col-md-2" style="text-align: right;">'
            +'    <button style="width: 120px;" class="btn btn-default btn-blue js-btn-submit">提交</button>'
            +'  </div>'
            +'</div>'
          +'</form>';
    this.component = [];
    this.data = data;
    this.url = url;
    this.body = body;
    this.$form = $(this.tplt);
    this.init()
}

Form.prototype.init = function(){
  var $form = this.$form ;
  var self = this;
  $form.find('.js-form-body').append(this.body).end()
    .find('.js-btn-submit').on('click',function(){
      self.submit()
            return false;
    }).end()
  this.component = [$form];
}
Form.prototype.setResult = function(val){
  this.$form.find('.js-result').val(JSON.stringify(JSON.parse(val)));
}
Form.prototype.getComponent = function(){
  return this.component;
}
Form.prototype.setFormBody = function(component){
    this.$form.find('.js-form-body').append(component);
}
Form.prototype.submit = function(){
    var data = this.$form.serializeArray();
    var params = {};
    var self = this;

    for(var i in data){
        data[i].value&&(params[data[i].name] = data[i].value);//过滤空字符串
    }

    $ .ajax({
        url:self.url,
        data:params,
        type:"post",
        success:function(result){
            self.setResult(result);
        }
    })
}


//================================================

var TreePanel = function(data){
  this.data = data;
  this.panelTplt = '<div class="panel panel-default">'
             +' <div class="panel-heading">'
             +'   <span class="panel-title"></span>'
             +'   <span class="panel-collaspe">收起</span>'
             +' </div>'
             +' <div class="panel-body">'
             +' </div>'
            +'</div>';
  this.formTplt = '<div class="form-group">'
                  +'<div class="row"><label class="col-md-2 control-label"></label>'
                  +'<div class="col-md-5">'
                  +'  <input type="text" name="" class="form-control"  placeholder="请输入……">'
                  +'</div><div class="col-md-2 js-en-name"></div></div>'
                  +'<div class="row"><div class="col-md-offset-2 col-md-5" style="padding-top:7px;">'
                  +'  <p class="notice"></p>'
                  +'</div></div>'
                +'</div>'
    this.listTplt = '<label class="col-md-2 control-label"></label>'
                  +'<div class="col-md-2">'
                  +'  <input type="text" name="" class="form-control"  placeholder="请输入……">'
                  +'</div>'
    this.component = [];
    this.init()
}

TreePanel.prototype.init = function(){
    if(!this.data) return;
    var self = this;
    //渲染子集panel
    function compleTplt(data,required){
        if (!data) {return};
        data = data instanceof Array?data:[data]
        var result = [];                

        for(var k in data){
            if(data[k].childs.length>0){
                var $childPanel = $(self.panelTplt)
                $childPanel.find('.panel-body').append(data[k].isListType?compleListTplt(data[k].childs,required):compleTplt(data[k].childs,required)).end()
                    .find('.panel-title').html((data[k].title+'('+data[k].enName+')')||'').end()
                result.push($childPanel);
            }else{
                var label = required&&data[k].isMust=='1'?'<span class="red">*</span>':""
                var $form = $(self.formTplt);
                label +='<span class="en-name">'+(data[k].enName?(data[k].enName):'')+'</span>' 
                $form.find('label').html(label).end()
                  .find('input').attr('name',data[k].fullParamName).attr('value',!!data[k].defaultValue?data[k].defaultValue:'').end()
                  .find('.notice').html(data[k].desc).end();
                result.push($form);
            }
        }
        return result
    }
    //渲染list
    function compleListTplt(data,required){
        if (!(data instanceof Array)||data.length<1) {return};
        var result = [],list = [];

        
        for(var i in data){
            //对fullParamName进行特殊处理，原数据格式goodsDetail[0].goodsCategory
            // var fullParamName = data[i].fullParamName.replace(/\[.*\]/,'['+i+']')
            var label = required&&data[i].isMust=='1'?'<span class="red">*</span>'+data[i].title:data[i].title
            var listTplt = '<label class="col-md-2 control-label">'+label+'<br /><span class="en-name">'+(data[i].enName?('('+data[i].enName+')'):'')+'</span>'+'</label>'
                  +'<div class="col-md-2">'
                  +'  <input type="text" name="'+data[i].fullParamName+'" class="form-control"  placeholder="请输入……">'
                  +'</div>'
            if (i%3==2) {//每行显示3个
                list.push(listTplt);
                var wrapListTplt = '<div class="form-group">'+list.join('')+'</div>'
                result.push(wrapListTplt);
                list = [];
            }else{
                list.push(listTplt);
            }
        }
        //清空list
        if (list.length>0) {
            var wrapListTplt = '<div class="form-group">'+list.join('')+'</div>'
            result.push(wrapListTplt);
        }

        function delList(el){
            var li = $(el).parents('.list-group').eq(0);
            var list = $(el).parents('.panel-body').eq(0).find('.list-group');
            var index = list.index(li);
            var isModifyName = index!=(list.length-1)
            li.remove();
            list.splice(index,1)
            
            if(isModifyName){
                //此时需要遍历修改name
                for(var i=0;i<list.length;i++){
                    $(list[i]).find('input').each(function(){
                        var name = $(this).attr('name').replace(/\[.*\]/,'['+i+']');
                        $(this).attr('name',name);
                    })
                }
            }

            
        }

        result = '<div class="list-group"><div class="list-header"><span class="glyphicon glyphicon-trash js-del"></span></div>'+result.join('')+'</div>'

        //还需要增加增加和删除功能
        var i = 1;
        var addBtn = '<div class="form-group">'
                     +'   <div class="col-md-2" style="text-align: right;">'
                     +'     <button class="btn btn-default btn-blue"><span class="glyphicon glyphicon-plus"></span>&nbsp;添加</button>'
                     +'   </div>'
                     +' </div>';
        var $addBtn = $(addBtn).find('button').on('click',function(){
            var $tplt = $(result).find('.js-del').on('click',function(){
                delList(this);
            }).end()
            $tplt.find('input').each(function(){
                var fullParamName = $(this).attr('name')
                $(this).attr('name',fullParamName.replace(/\[.*\]/,'['+i+']'))
            })
            $(this).parents('.form-group').eq(0).before($tplt);
            i++;
            return false;
        }).end();

        //删除功能，在删除的时候，需要重新遍历list，修改name
        var $result = $(result).find('.js-del').on('click',function(){
            delList(this)
        }).end()
        return [$result,$addBtn]
    }
    
    //渲染一级panel
    var requiredAry = [],optionAry = [];
    for(var k in this.data){
        if (this.data[k].isMust=='1') {
            requiredAry = requiredAry.concat(compleTplt(this.data[k],true))
        }else{
            optionAry = optionAry.concat(compleTplt(this.data[k],false))
        }
    }
  
    var $requiredPanel = requiredAry.length>0&&$(this.panelTplt);
    var $optionPanel = optionAry.length>0&&$(this.panelTplt);
    requiredAry.length>0&&$requiredPanel.find('.panel-body').append(requiredAry).end()
        .find('.panel-title').eq(0).html('必填项').end().end()
        .on('click','.panel-collaspe',function() {
            var $panelBody = $(this).parents('.panel').eq(0).find('.panel-body').eq(0);
            $panelBody.hasClass('hide')
                ?$panelBody.removeClass('hide')&&$(this).html('收起')
                :$panelBody.addClass('hide')&&$(this).html('展开')
          });

    optionAry.length>0&&$optionPanel.find('.panel-body').append(optionAry).end()
    .find('.panel-title').eq(0).html('可选项').end().end()
    .on('click','.panel-collaspe',function() {
        var $panelBody = $(this).parents('.panel').eq(0).find('.panel-body').eq(0);
        $panelBody.hasClass('hide')
                ?$panelBody.removeClass('hide')&&$(this).html('收起')
                :$panelBody.addClass('hide')&&$(this).html('展开')
      });
    this.component = this.component.concat([$requiredPanel,$optionPanel])
}  

TreePanel.prototype.getComponent = function(){
    return this.component;
}