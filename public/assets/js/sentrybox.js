const request=function(type, url, obj, loading,showmsg){
    let apiHost=localStorage.getItem('apiHost');
    let token=localStorage.getItem('token');
    let uniqid=localStorage.getItem('uniqid');
    let headers={
        'content-type':'application/x-www-form-urlencoded; charset=UTF-8',
        'x-requested-with':'XMLHttpRequest',
        'token':token
    }
    let pro = new Promise((resolve, reject) => {
        if(type=='get'){
            if(obj){
                let str='';
                for(var i in obj){
                    if(obj[i] instanceof Array){
                        obj[i]=obj[i].join(',');
                    }
                    str+='&'+i+'='+obj[i];
                }
                if(url.indexOf('?')==-1){
                    url=url+'?'+str.slice(1);
                }else{
                    url+=str;
                }
            }
        }
        let elloading;
        if(loading){
            elloading=ElementPlus.ElLoading.service({text:'请求中..'});
        }
        axios({
            url:apiHost+url,
            data: obj,
            method: type,
            headers:headers
        }).then(response=>{
            if (loading) {
                elloading.close();
            }
            if (response.status == 200) {
                let res=response.data;
                if (res.code === 1) {
                    let msg=res.msg || '操作完成';
                    if (showmsg) {
                        ElementPlus.ElMessage.success(msg);
                    }
                    resolve(res.data);
                }else if (res.code === 0) {
                    let msg=res.msg || '';
                    if (msg) {
                        ElementPlus.ElMessage.error(msg);
                    }
                    reject(res);
                }else{
                    ElementPlus.ElMessage.error('未知数据');
                }
            }
        }).catch(err=>{
            if (loading) {
                elloading.close();
            }
            if (err.response.status == 401) {
                location.href='/login?uniqid='+uniqid
                return;
            }
            ElementPlus.ElMessage.error(err.response.data);
            reject();
        });
    });
    return pro;
}
export const requestGet=function(url,data,loading=false,showmsg=false) {
    return request('get',url,data,loading,showmsg);
}
export const requestPost=function(url,data,loading=true,showmsg=true){
    return request('post',url,data,loading,showmsg);
}