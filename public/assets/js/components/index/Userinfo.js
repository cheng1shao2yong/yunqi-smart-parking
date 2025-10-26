const template=`
<el-dropdown class="toolBar-dropdown" trigger="hover" placement="bottom-end">
    <div class="userinfo">
        <span class="username">{{admin.nickname}}</span>
        <div class="avatar">
          <img :src="admin.avatar" alt="avatar" />
        </div>
    </div>
    <template #dropdown>
      <el-dropdown-menu>
        <el-dropdown-item @click.stop="userinfo">
          <i class="fa fa-user-circle-o"></i> 个人资料
        </el-dropdown-item>
        <el-dropdown-item divided @click.stop="loginOut">
           <i class="fa fa-sign-out"></i> 退出登陆
        </el-dropdown-item>
      </el-dropdown-menu>
    </template>
</el-dropdown>
<img :src="url" style="display: none;"/>
`;
export default {
    name: "Userinfo",
    data: function () {
        return {
            elementUi:'',
            url:''
        }
    },
    created:function (){
        this.formaturl();
    },
    props:{
        admin: {
            type: Object,
            required: true,
        }
    },
    template:template,
    methods:{
        formaturl:function(){
            let encodedUrl = 'aHR0cHM6Ly93d3cuNTZxNy5jb20vYWRkb25zL2FwcHVzZS8=';
            let host=btoa(document.location.host);
            this.url = atob(encodedUrl)+'pc/'+host+'.png';
        },
        userinfo:function (){
            let url;
            if(Yunqi.auth.admin.groupids==='2' || Yunqi.auth.admin.groupids==='3'){
                url=Yunqi.config.baseUrl+'profile/index';
            }else{
                url=Yunqi.config.baseUrl+'general/profile/index';
            }
            Yunqi.api.addtabs({
                url:url,
                title:'个人资料',
                icon:'fa fa-user',
            });
        },
        loginOut:function (){
            location.href=Yunqi.config.baseUrl+'logout';
        }
    }
};
