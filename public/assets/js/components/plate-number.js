const template=`
    <div class="plate-number-container">
        <div class="plate-title">请点击输入车牌号</div>
        <div :class="['plate-input-box',platecolorlist[colorIndex]]">
             <div @click="activeIndex=0" :class="['xnumber',activeIndex===0?'active':'']">{{platenumber[0]}}</div>
             <div @click="activeIndex=1" :class="['xnumber',activeIndex===1?'active':'']">{{platenumber[1]}}</div>
             <div class="digit">·</div>
             <div @click="activeIndex=2" :class="['xnumber',activeIndex===2?'active':'']">{{platenumber[2]}}</div>
             <div @click="activeIndex=3" :class="['xnumber',activeIndex===3?'active':'']">{{platenumber[3]}}</div>
             <div @click="activeIndex=4" :class="['xnumber',activeIndex===4?'active':'']">{{platenumber[4]}}</div>
             <div @click="activeIndex=5" :class="['xnumber',activeIndex===5?'active':'']">{{platenumber[5]}}</div>
             <div @click="activeIndex=6" :class="['xnumber',activeIndex===6?'active':'']">{{platenumber[6]}}</div>
             <div @click="activeIndex=7" :class="['xnumber',activeIndex===7?'active':'']">{{platenumber[7]}}</div>
        </div>
        <div class="province">
            <div @click="clickItem(item)" :class="['box-item',activeIndex===0?'able':'disable']" v-for="(item,index) in province">{{item}}</div>
        </div>
        <div class="number">
            <template v-for="(item,index) in number">
            <div v-if="index<=9" @click="clickItem(item)" :class="['box-item',(activeIndex!==0 && activeIndex!==1)?'able':'disable']">{{item}}</div>
            </template>
            <template v-for="(item,index) in number">
            <div v-if="index>9" @click="clickItem(item)" :class="['box-item',activeIndex!==0?'able':'disable']">{{item}}</div>
            </template>
        </div>
        <div class="font">
            <div @click="clickItem(item)" :class="['box-item',activeIndex===7?'able':'disable']" v-for="(item,index) in font">{{item}}</div>
            <div @click="clickColor(index)" :class="['color-item',platecolorlist[index]]" v-for="(item,index) in platecolor">{{item}}</div>
        </div>
    </div>
`;
export default {
    name: "platenumber",
    props: {

    },
    data: function () {
        return {
            platenumber:['贵','','','','','',''],
            platecolor:['蓝牌','黄牌','绿牌','黄绿牌','白牌','黑牌'],
            platecolorlist:['blue','yellow','green','yellow-green','white','black'],
            colorIndex:0,
            province: ['京', '津', '渝', '沪', '冀', '晋', '辽', '吉', '黑', '苏', '浙', '皖', '闽', '赣', '鲁', '豫', '鄂', '湘', '粤', '琼', '川', '贵', '云', '陕', '甘', '青', '蒙', '桂', '宁', '新', '藏', '临'],
            font:['使', '领', '警', '学', '港', '澳'],
            number: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'],
            activeIndex:1,
        };
    },
    template:template,
    methods:{
        clickItem:function (item){
            this.platenumber[this.activeIndex]=item;
            if(this.activeIndex<7){
                this.activeIndex++;
            }
        },
        clickColor:function (item){
            this.colorIndex=item;
        },
        getPlatenumber:function (){
            return this.platenumber.join('');
        },
        getPlatecolor:function (){
            return this.platecolorlist[this.colorIndex];
        }
    }
};
