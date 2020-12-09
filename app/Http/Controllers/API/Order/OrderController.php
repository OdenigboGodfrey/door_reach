<?php

namespace App\Http\Controllers\API\Order;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Order;
use App\Models\OrderAttachment;
use App\Models\OrderDetail;
use App\Models\OrderSpecification;
use Illuminate\Http\Request;
use App\Utilities\Utility;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Image as Manipulator;

class OrderController extends Controller
{
    private $date = '';
    private $out = '';

    public function __construct()
    {
        $this->out = new \Symfony\Component\Console\Output\ConsoleOutput();
        $this->date = Carbon::now()->toDateString();
    }

    public function create_order(Request $request) {
        $validator = Utility::validator($request->all(),[
            'specifications' => 'required|string',
            'details' => 'required|string',
            'title' => 'required|string',
        ]);

        if ($validator['failed']) {
            return \prepare_json(Utility::$negative, ['messages' => $validator['messages']],'',$status_code=Utility::$_422);
        }

        try {

            $data = $request->all();

            $client = auth()->guard('api-client')->user();

            //save order
            $order = Order::create([
               'title' => str_replace('"','',$data['title']),
               'status' => Utility::$neutral,
                'client_id' => $client->id,
            ]);
            
            $details = json_decode($data['details']);
            foreach ($details as $item => $value) {
                $detail = OrderDetail::create([
                   'order_id' => $order->id,
                   'detail' => $value
                ]);
            }

            $specifications = json_decode($data['specifications']);
            foreach ($specifications as $item) {
                $specifications = OrderSpecification::create([
                    'order_id' => $order->id,
                    'title' => $item->title,
                    'content' => $item->content,
                ]);
            }


            if ($request->hasFile('attachments')) {
                $files = $request->file('attachments');
//                dd($files);
                $destinationPathX250 = public_path('uploads/orders/'. $order->id .'/attachments/');
                File::makeDirectory($destinationPathX250, $mode=0777, true, true);


                foreach ($files as $file) {
                    Manipulator::make($file)->resize(null, 250, function($constraint) {
                        $constraint->aspectRatio();
                    })->save($destinationPathX250.$file->getClientOriginalName());

                    OrderAttachment::create([
                        'order_id' => $order->id,
                        'file' => $file->getClientOriginalName(),
                        'ext' => $file->getClientOriginalExtension(),
                    ]);
                }


            }
            $order['details'] = $order->details()->get();
            $order['specifications'] = $order->specifications()->get();
            $order['attachments'] = $order->attachments()->get();

            return \prepare_json(Utility::$positive, ["order" => $order],\get_api_string('generic_ok'), 201);
        }
        catch (\Exception $ex) {
            return \prepare_json(Utility::$error, [],\get_api_string('error_occurred').$ex->getMessage(), Utility::$_500);
        }
    }

    public function get_orders(Request $request) {
        /** type 0 => all orders, 1 => [pass status to get specific orders]  ***/
        $validator = Utility::validator($request->all(),[
            'type' => 'required|numeric',
            'status' => 'numeric',
            'client_id' => 'numeric',
        ]);

        if ($validator['failed']) {
            return \prepare_json(Utility::$negative, ['messages' => $validator['messages']],'',$status_code=Utility::$_422);
        }

        try {
            $data = $request->all();
            $client = "";
            $client_id_exist = array_key_exists('client_id', $data);
            $status_exist = array_key_exists('status', $data);

            if (!$client_id_exist) {
                //user is accessing this link
                $client = auth()->guard('api-client')->user();
            }
            else {
                $client = Client::where('id', $data['client_id'])->first();

                if (!$client) {
                    return \prepare_json(Utility::$negative, [],\get_api_string('not_found', 'Client'));
                }
            }


            if ($data['type'] == 1 && !$status_exist) {
                return \prepare_json(Utility::$negative, ['messages' => "The status is required for  type of 1"],'',$status_code=Utility::$_422);
            }

            if (!$status_exist) {
                $orders = Order::where('client_id', $client->id)->get();
            }
            else {
                $orders = Order::where('client_id', $client->id)->where('status', $data['status'])->get();
            }

            return \prepare_json(Utility::$positive, ['orders' => $orders],get_api_string('generic_ok'));
        }
        catch (\Exception $ex) {
            return \prepare_json(Utility::$error, [],\get_api_string('error_occurred').$ex->getMessage(), Utility::$_500);
        }
    }
}
