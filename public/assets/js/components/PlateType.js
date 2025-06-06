const template=`
      <el-tag effect="dark" :color="tag.color" v-if="tag.color" :hit="false">{{tag.name}}</el-tag>
      <el-tag effect="dark" :type="tag.type" v-if="tag.type" :hit="false">{{tag.name}}</el-tag>
      <el-tag type="info" v-if="plate_type=='white'">{{tag.name}}</el-tag>
      <el-tag style="color: #fff;background: linear-gradient(to right,#e6a23c,#67c23a)" v-if="plate_type=='yellow-green'">{{tag.name}}</el-tag>
      <span v-if="!plate_type">{{tag.name}}</span>
`;
export default {
    name: "PlateType",
    data: function () {
        return {
            tag:{}
        }
    },
    props:{
        plate_number: {
            type:String,
            default:''
        },
        plate_type: {
            type:String,
            default:'blue'
        }
    },
    mounted:function (){
        this.init(this.plate_number,this.plate_type);
    },
    template:template,
    methods:{
        init:function (plate_number,plate_type){
            let tag={name:plate_number};
            if(plate_type=='blue'){
                tag.color='#409eff';
            }
            if(plate_type=='green'){
                tag.type='success';
            }
            if(plate_type=='yellow'){
                tag.type='warning';
            }
            if(plate_type=='black'){
                tag.color='#000';
            }
            this.tag= tag;
        },
        setPlate:function (plate_number,plate_type){
            this.init(plate_number,plate_type);
        }
    }
};
