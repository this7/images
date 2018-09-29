<?php
/**
 * this7 PHP Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright 2016-2018 Yan TianZeng<qinuoyun@qq.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://www.ub-7.com
 */
namespace this7\images\build;
use \this7\framework\ErrorCode;

class base {
    #水印图像
    protected $image = '';
    #位置  1~9九个位置  0为随机
    protected $pos = 9;
    #透明度
    protected $pct = 60;
    #压缩比
    protected $quality = 80;
    #水印文字
    protected $text = 'UB7';
    #文字颜色
    protected $text_color = '#f00f00';
    #文字大小
    protected $text_size = 12;
    #水印字体
    protected $font = __DIR__ . '/font.ttf';

    public function imagesURL($url) {
        $string = trim($_SERVER['REQUEST_URI'], "/");
        if (substr($string, 0, strlen("images")) === "images") {
            $_GET['type'] = "images";
            $pieces       = explode("/", $string);
            $info         = explode("_", $pieces[1]);
            $path         = ROOT_DIR . DS . "upload" . DS . $info[0] . ".json";
            if (is_file($path)) {
                $code = file_get_contents($path);
                $code = to_array($code);
                if (isset($info[1])) {
                    $k     = $info[1];
                    $image = isset($code[$k]) ? $code[$k] : $code['default'];
                } else {
                    $image = $code['default'];
                }
            } else {
                echo "图片不存在";
                exit();
            }
            $imgInfo = base64_decode($image);
            $imgInfo = imagecreatefromstring($imgInfo);
            $imgExt  = get_extension($code['type']);
            $mime    = $code['type'];
            #清除之前的缓存
            if (ob_get_level()) {
                ob_end_clean();
            }
            header('Content-Type:' . $mime);
            $quality = 100;
            if ($imgExt == 'png') {
                $quality = 9;
            }
            //输出质量,JPEG格式(0-100),PNG格式(0-9)
            $getImgInfo = "image" . substr($imgExt, 1);
            $getImgInfo($imgInfo, null, $quality); //2.将图像输出到浏览器或文件。如: imagepng ( resource $image )
            imagedestroy($imgInfo);
            exit();
        }
        return $url;
    }

    private function FunctionName($value = '') {
        # code...
    }

    /**
     * 图片检测
     *
     * @param string $img
     * @return bool
     */
    private function check($img) {
        if (is_array($img)) {
            return false;
        }
        $type    = [".jpg", ".jpeg", ".png", ".gif"];
        $imgType = strtolower(strrchr($img, '.'));

        return extension_loaded('gd')
        && in_array(
            $imgType,
            $type
        );
    }

    /**
     * 头像上传
     * @Author   Sean       Yan
     * @DateTime 2018-09-28
     * @return   [type]     [description]
     * code: {
     *      name: '',
     *      data: '',
     *      width: 0,
     *      height: 0,
     *      zoom: 1,
     *      type: '',
     *      size: '',
     *      tailor: {
     *          width: 200,
     *          height: 200,
     *          x: 0,
     *          y: 0
     *      },
     *      variety: [
     *          [200, 200],
     *          [100, 100],
     *          [60, 60]
     *      ]
     * }
     */
    public function pictureUpload() {
        if (empty($_POST['data'])) {
            error("提交的数据不能为空", ErrorCode::$submittedDataNotRmpty);
        }
        $image = $_POST;
        #用于剔除base64图片前缀
        if (strstr($image['data'], ",")) {
            $rence         = explode(',', $image['data']);
            $image['data'] = $rence[1];
        };
        $width  = $image['zoom'] * $image['width'];
        $height = $image['zoom'] * $image['height'];
        #先执行图片缩放
        $img = $this->thumb($image, false, $width, $height);
        #获取裁剪信息
        $tailor = $image['tailor'];
        #获取缩略图种类
        $variety = $image['variety'];
        #执行数据裁剪
        $img = $this->tailorImg($img, $tailor['width'], $tailor['height'], $tailor['x'], $tailor['y'], $variety[0][0], $variety[0][1]);
        #unique唯一值
        $unique = md5($img);
        #设置元素信息
        $imageInfo = array(
            "default" => $img,
            "type"    => $image['type'],
            "width"   => $variety[0][0],
            "height"  => $variety[0][1],
        );
        #删除第一个缩放元素
        unset($variety[0]);
        #循环获取后面元素
        foreach ($variety as $key => $value) {
            $image['data']        = $img;
            $image['width']       = $imageInfo['width'];
            $image['height']      = $imageInfo['height'];
            $imageInfo[$value[0]] = $this->thumb($image, false, $value[0], $value[1]);
        }
        $path = ROOT_DIR . DS . "upload/" . $unique . ".json";
        to_mkdir($path, to_json($imageInfo), true, true);
        $data = array(
            "code" => 0,
            "msg"  => "success",
            "data" => ROOT . "/images/" . $unique,
        );
        echo to_json($data);
        exit();
    }

    /**
     * @Author   Sean       Yan
     * @DateTime 2018-09-27
     * @param    string     $img      裁剪图片地址或Base64数据
     * @param    number     $width    裁剪框宽度
     * @param    number     $height   裁剪框高度
     * @param    number     $x        裁剪X值
     * @param    number     $y        裁剪Y值
     * @param    number     $zoom_w   裁剪缩放宽度
     * @param    number     $zoom_h   裁剪缩放
     * @return   string               图片Base64数据
     */
    public function tailorImg($img = '', $width = 200, $height = 200, $x = 0, $y = 0, $zoom_w = 200, $zoom_h = 200) {
        if ($img = base64_decode($img)) {
            $image_data = $img;
        } else {
            $image_data = file_get_contents($img);
        }
        #创建源图的实例
        $src = imagecreatefromstring($image_data);
        #将裁剪区域复制到新图片上，并根据源和目标的宽高进行缩放或者拉升
        $new_image = imagecreatetruecolor($width, $height);
        imagecopyresampled($new_image, $src, 0, 0, $x, $y, $width, $height, $zoom_w, $zoom_h);
        #清除之前的缓存
        if (ob_get_level()) {
            ob_end_clean();
        }
        #输出图片
        ob_start();
        imagejpeg($new_image);
        $content = ob_get_clean();
        imagedestroy($src);
        imagedestroy($new_image);
        return base64_encode($content);
    }

    /**
     * 获得缩略图的尺寸信息
     *
     * @param $imgWidth    原图宽度
     * @param $imgHeight   原图高度
     * @param $thumbWidth  缩略图宽度
     * @param $thumbHeight 缩略图的高度
     * @param $thumbType   处理方式
     *                     1 固定宽度  高度自增 2固定高度  宽度自增 3固定宽度  高度裁切
     *                     4 固定高度 宽度裁切 5缩放最大边 原图不裁切
     *
     * @return mixed
     */
    private function thumbSize($imgWidth, $imgHeight, $thumbWidth, $thumbHeight, $thumbType) {
        #初始化缩略图尺寸
        $w = $thumbWidth;
        $h = $thumbHeight;
        #初始化原图尺寸
        $cuthumbWidth  = $imgWidth;
        $cuthumbHeight = $imgHeight;
        switch ($thumbType) {
        case 1:
            #固定宽度  高度自增
            $h = $thumbWidth / $imgWidth * $imgHeight;
            break;
        case 2:
            #固定高度  宽度自增
            $w = $thumbHeight / $imgHeight * $imgWidth;
            break;
        case 3:
            #固定宽度  高度裁切
            $cuthumbHeight = $imgWidth / $thumbWidth * $thumbHeight;
            break;
        case 4:
            #固定高度  宽度裁切
            $cuthumbWidth = $imgHeight / $thumbHeight * $thumbWidth;
            break;
        case 5:
            #缩放最大边 原图不裁切
            if (($imgWidth / $thumbWidth) > ($imgHeight / $thumbHeight)) {
                $h = $thumbWidth / $imgWidth * $imgHeight;
            } elseif (($imgWidth / $thumbWidth) < ($imgHeight
                / $thumbHeight)
            ) {
                $w = $thumbHeight / $imgHeight * $imgWidth;
            } else {
                $w = $thumbWidth;
                $h = $thumbHeight;
            }
            break;
        default:
            #缩略图尺寸不变，自动裁切图片
            if (($imgHeight / $thumbHeight) < ($imgWidth / $thumbWidth)) {
                $cuthumbWidth = $imgHeight / $thumbHeight * $thumbWidth;
            } elseif (($imgHeight / $thumbHeight) > ($imgWidth / $thumbWidth)) {
                $cuthumbHeight = $imgWidth / $thumbWidth * $thumbHeight;
            }
        }
        $arr[0] = $w;
        $arr[1] = $h;
        $arr[2] = $cuthumbWidth;
        $arr[3] = $cuthumbHeight;

        return $arr;
    }
    /**
     * 图片压缩处理
     *
     * @param        $img         原图
     * @param string $outFile     另存文件名
     * @param int    $thumbWidth  缩略图宽度
     * @param int    $thumbHeight 缩略图高度
     * @param int    $thumbType   裁切图片的方式
     *                            1 固定宽度  高度自增 2固定高度  宽度自增 3固定宽度  高度裁切
     *                            4 固定高度 宽度裁切 5缩放最大边 原图不裁切 6缩略图尺寸不变，自动裁切最大边
     *
     * @return bool|string
     */
    public function thumb($img, $outFile, $thumbWidth = 200, $thumbHeight = 200, $thumbType = 1) {
        #基础配置
        $thumbType   = $thumbType;
        $thumbWidth  = $thumbWidth;
        $thumbHeight = $thumbHeight;
        #检查图片类型
        if ($this->check($img)) {
            #获得图像信息
            $imgInfo   = getimagesize($img);
            $imgWidth  = $imgInfo[0];
            $imgHeight = $imgInfo[1];
            $imgType   = image_type_to_extension($imgInfo[2]);
            #原始图像资源
            $func   = "imagecreatefrom" . substr($imgType, 1);
            $resImg = $func($img);
        } elseif (is_array($img)) {
            $imgData   = base64_decode($img['data']);
            $imgWidth  = $img['width'];
            $imgHeight = $img['height'];
            $imgType   = get_extension($img['type']);
            #原始图像资源
            $resImg = imagecreatefromstring($imgData);
        } else {
            return false;
        }
        #获得相关尺寸
        $thumb_size = $this->thumbSize(
            $imgWidth,
            $imgHeight,
            $thumbWidth,
            $thumbHeight,
            $thumbType
        );
        #缩略图的资源
        if ($imgType == '.gif') {
            $res_thumb = imagecreate($thumb_size[0], $thumb_size[1]);
            $color     = imagecolorallocate($res_thumb, 255, 0, 0);
        } else {
            $res_thumb = imagecreatetruecolor($thumb_size[0], $thumb_size[1]);
            imagealphablending($res_thumb, false); #关闭混色
            imagesavealpha($res_thumb, true); #储存透明通道
        }
        #绘制缩略图X
        if (function_exists("imagecopyresampled")) {
            imagecopyresampled(
                $res_thumb,
                $resImg,
                0,
                0,
                0,
                0,
                $thumb_size[0],
                $thumb_size[1],
                $thumb_size[2],
                $thumb_size[3]
            );
        } else {
            imagecopyresized(
                $res_thumb,
                $resImg,
                0,
                0,
                0,
                0,
                $thumb_size[0],
                $thumb_size[1],
                $thumb_size[2],
                $thumb_size[3]
            );
        }
        #处理透明色
        if ($imgType == '.gif') {
            imagecolortransparent($res_thumb, $color);
        }

        #设置图像函数
        $func = "image" . substr($imgType, 1);

        #是否输出图像
        if ($outFile) {
            is_dir(dirname($outFile)) || mkdir(dirname($outFile), 0755, true);
            $func($res_thumb, $outFile);
            $content = true;
        } else {
            #清除之前的缓存
            if (ob_get_level()) {
                ob_end_clean();
            }
            #输出图片
            ob_start();
            $func($res_thumb);
            $content = ob_get_clean();
            $content = base64_encode($content);
        }
        if (isset($resImg)) {
            imagedestroy($resImg);
        }
        if (isset($res_thumb)) {
            imagedestroy($res_thumb);
        }
        return $content;
    }

    /**
     * 水印处理
     *
     * @param string $img      原图像
     * @param string $outImg   加水印后的图像
     * @param string $pos      水印位置
     * @param string $waterImg 水印图片
     * @param string $pct      透明度
     * @param string $text     文字水印内容
     *
     * @return bool
     */
    public function water($img, $outImg, $pos = null, $waterImg = null, $text = null, $pct = null) {
        #验证原图像
        if (!$this->check($img)) {
            return false;
        }
        #验证水印图像
        $waterImg   = $waterImg ?: $this->image;
        $waterImgOn = $this->check($waterImg) ? 1 : 0;

        #水印位置
        $pos = $pos ?: $this->pos;
        #水印文字
        $text = $text ?: $this->text;
        #水印透明度
        $pct       = $pct ?: $this->pct;
        $imgInfo   = getimagesize($img);
        $imgWidth  = $imgInfo[0];
        $imgHeight = $imgInfo[1];
        #获得水印信息
        if ($waterImgOn) {
            $waterInfo   = getimagesize($waterImg);
            $waterWidth  = $waterInfo[0];
            $waterHeight = $waterInfo[1];
            switch ($waterInfo[2]) {
            case 1:
                $w_img = imagecreatefromgif($waterImg);
                break;
            case 2:
                $w_img = imagecreatefromjpeg($waterImg);
                break;
            case 3:
                $w_img = imagecreatefrompng($waterImg);
                break;
            }
        } else {
            if (empty($text) || strlen($this->text_color) != 7) {
                return false;
            }
            $textInfo = imagettfbbox(
                $this->text_size,
                0,
                $this->font,
                $text
            );
            $waterWidth  = $textInfo[2] - $textInfo[6];
            $waterHeight = $textInfo[3] - $textInfo[7];
        }
        #建立原图资源
        if ($imgHeight < $waterHeight || $imgWidth < $waterWidth) {
            return false;
        }
        switch ($imgInfo[2]) {
        case 1:
            $resImg = imagecreatefromgif($img);
            break;
        case 2:
            $resImg = imagecreatefromjpeg($img);
            break;
        case 3:
            $resImg = imagecreatefrompng($img);
            break;
        }
        #水印位置处理方法
        switch ($pos) {
        case 1:
            $x = $y = 25;
            break;
        case 2:
            $x = ($imgWidth - $waterWidth) / 2;
            $y = 25;
            break;
        case 3:
            $x = $imgWidth - $waterWidth;
            $y = 25;
            break;
        case 4:
            $x = 25;
            $y = ($imgHeight - $waterHeight) / 2;
            break;
        case 5:
            $x = ($imgWidth - $waterWidth) / 2;
            $y = ($imgHeight - $waterHeight) / 2;
            break;
        case 6:
            $x = $imgWidth - $waterWidth;
            $y = ($imgHeight - $waterHeight) / 2;
            break;
        case 7:
            $x = 25;
            $y = $imgHeight - $waterHeight;
            break;
        case 8:
            $x = ($imgWidth - $waterWidth) / 2;
            $y = $imgHeight - $waterHeight;
            break;
        case 9:
            $x = $imgWidth - $waterWidth - 10;
            $y = $imgHeight - $waterHeight;
            break;
        default:
            $x = mt_rand(25, $imgWidth - $waterWidth);
            $y = mt_rand(25, $imgHeight - $waterHeight);
        }
        if ($waterImgOn) {
            if ($waterInfo[2] == 3) {
                imagecopy(
                    $resImg,
                    $w_img,
                    $x,
                    $y,
                    0,
                    0,
                    $waterWidth,
                    $waterHeight
                );
            } else {
                imagecopymerge(
                    $resImg,
                    $w_img,
                    $x,
                    $y,
                    0,
                    0,
                    $waterWidth,
                    $waterHeight,
                    $pct
                );
            }
        } else {
            $r     = hexdec(substr($this->text_color, 1, 2));
            $g     = hexdec(substr($this->text_color, 3, 2));
            $b     = hexdec(substr($this->text_color, 5, 2));
            $color = imagecolorallocate($resImg, $r, $g, $b);
            imagettftext(
                $resImg,
                $this->text_size,
                0,
                $x,
                $y,
                $color,
                $this->font,
                $text
            );
        }
        switch ($imgInfo[2]) {
        case 1:
            imagegif($resImg, $outImg);
            break;
        case 2:
            imagejpeg($resImg, $outImg, $this->quality);
            break;
        case 3:
            imagepng($resImg, $outImg);
            break;
        }
        if (isset($resImg)) {
            imagedestroy($resImg);
        }
        if (isset($w_img)) {
            imagedestroy($w_img);
        }

        return true;
    }
}