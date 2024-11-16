<?php namespace App\Controllers\Cart;
use App\Controllers\BaseController;
use App\Libraries\Mycart;
use CodeIgniter\HTTP\ResponseInterface;

class Cart extends BaseController {

    protected $validation;
    protected $session;
    protected $cart;

    public function __construct()
    {
        $this->validation = \Config\Services::validation();
        $this->session = \Config\Services::session();
        $this->cart = new Mycart();
    }

    /**
     * @description This method provides cart page view
     * @return void
     */
    public function index()
    {
        $settings = get_settings();
        $data['keywords'] = $settings['meta_keyword'];
        $data['description'] = $settings['meta_description'];
        $data['title'] = 'Shopping Cart';

        $data['page_title'] = 'Cart';
        echo view('Theme/'.$settings['Theme'].'/header',$data);
        echo view('Theme/'.$settings['Theme'].'/Cart/index');
        echo view('Theme/'.$settings['Theme'].'/footer');
    }

    /**
     * @description This method provides option data exist check
     * @return void
     */
    public function checkoption(){
        $product_id = $this->request->getPost('product_id');
        $table = DB()->table('cc_product_option');
        $check = $table->where('product_id',$product_id)->countAllResults();
        print !empty($check) ? false : true;
    }

    /**
     * @description This method provides cart data exist check
     * @return void
     */
    public function cart_empty_check(){
        print empty($this->cart->contents())? false : true;
    }

    /**
     * @description This method provides cart data store.
     * @return void
     */
    public function addToCart(){


        $product_id = $this->request->getPost('product_id');
        $qty = $this->request->getPost('qtyall');

        $size = $this->request->getPost('size');
        $color = $this->request->getPost('color');

        $name = get_data_by_id('name','cc_products','product_id',$product_id);
        $price = get_data_by_id('price','cc_products','product_id',$product_id);
        $specialprice = get_data_by_id('special_price','cc_product_special','product_id',$product_id);
        if (!empty($specialprice)){
            $price = $specialprice;
        }
        $check = $this->check_qty($product_id , $qty);
        if ($check == true) {
            $data = array(
                'id' => $product_id,
                'name' => strval($name),
                'qty' => $qty,
                'price' => $price,
                'color' => $color,
                'size' => $size,
            );
            $this->cart->insert($data);
            print 'Successfully add to cart';
        }else{
            print 'not enough quantity!';
        }
    }

    /**
     * @description This method provides cart data store.
     * @return void
     */
    public function addtocartdetail(){
        $product_id = $this->request->getPost('product_id');
        $qty = $this->request->getPost('qty');

        $totalOptionPrice = 0;
        foreach(get_all_data_array('cc_option') as $vl) {
            $data[strtolower($vl->name)] = $this->request->getPost(strtolower($vl->name));

            $table = DB()->table('cc_product_option');
            $option = $table->where('option_value_id',$data[strtolower($vl->name)])->where('product_id',$product_id)->get()->getRow();

            if (!empty($option)) {
                if (empty($option->subtract)){
                    $totalOptionPrice = $totalOptionPrice + $option->price;
                }else{
                    $totalOptionPrice = $totalOptionPrice - $option->price;
                }
            }
        }

        $name = get_data_by_id('name','cc_products','product_id',$product_id);
        $price = get_data_by_id('price','cc_products','product_id',$product_id);
        $specialprice = get_data_by_id('special_price','cc_product_special','product_id',$product_id);
        if (!empty($specialprice)){
            $price = $specialprice;
        }

        $totalPrice = $price + $totalOptionPrice;
        $data = array(
            'id' => $product_id,
            'name' => strval($name),
            'qty' => $qty,
            'price' => $totalPrice,
        );

        foreach(get_all_data_array('cc_option') as $v) {
            $data['op_'.strtolower($v->name)] = $this->request->getPost(strtolower($v->name));
        }

        $check = $this->check_qty($product_id , $qty);
        if ($check == true) {
            $this->cart->insert($data);
            print 'Successfully add to cart';
        }else{
            print 'not enough quantity!';
        }
    }

    /**
     * @description This method provides cart data store.
     * @return void
     */
    public function addToCartGroup(){

        $productId = $this->request->getPost('both_product[]');

        foreach ($productId as $product_id) {
            $name = get_data_by_id('name', 'cc_products', 'product_id', $product_id);
            $price = get_data_by_id('price', 'cc_products', 'product_id', $product_id);
            $specialprice = get_data_by_id('special_price', 'cc_product_special', 'product_id', $product_id);
            if (!empty($specialprice)) {
                $price = $specialprice;
            }
            $data = array(
                'id' => $product_id,
                'name' => strval($name),
                'qty' => 1,
                'price' => $price
            );
            $this->cart->insert($data);
        }
        print 'Successfully add to cart';
    }

    /**
     * @description This method provides cart data update.
     * @return ResponseInterface
     */
    public function updateToCart(){
        $rowid = $this->request->getPost('rowid');
        $qty = $this->request->getPost('qty');
        $data = array(
            'rowid' => $rowid,
            'qty'   => $qty
        );


        foreach($this->cart->contents() as $row) {
            if ($row['rowid'] == $rowid) {
                $check = $this->check_qty($row['id'], $qty);
                if ($check == true) {
                    $this->cart->update($data);
                    $data['message'] = 'Successfully update to cart';
                } else {
                    $data['message'] = 'not enough quantity!';
                }
            }
        }
        $data['cartTotal'] = Cart()->total();

        return $this->response->setJSON($data);
    }

    /**
     * @description This method provides cart data remove.
     * @return void
     */
    public function removeToCart(){
        $rowid = $this->request->getPost('rowid');
        $this->cart->remove($rowid);

        if (empty($this->cart->contents())){
            unset($_SESSION['coupon_discount']);
        }

        print Cart()->total();
    }

    /**
     * @description This method provides product qty check
     * @param int $productID
     * @param int $qty
     * @return bool
     */
    private function check_qty($productID , $qty){
        $table = DB()->table('cc_products');
        $data = $table->where('product_id',$productID)->get()->getRow();
        return ($data->quantity >= $qty)? true : false;
    }


}
