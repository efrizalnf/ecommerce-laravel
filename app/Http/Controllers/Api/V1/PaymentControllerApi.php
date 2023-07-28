<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class PaymentControllerApi extends Controller
{
    public function payment(Request $request)
    {

        if ($request->has('callback')) {
            Order::where(['id' => $request->order_id])->update(['callback' => $request['callback']]);
        }

        $secret_key = 'Basic ' . config('xendit.xendit_key');
        $dataxendit = Http::withHeaders([
            'Authorization' => $secret_key
        ])->post('https://api.xendit.co/v2/invoices', [
            'user_id' => $request['user_id'],
            'external_id' => $request->order_id,
            'amount' => $request->total_amount,
            'payer_email' => $request->payer_email,
            'description' => $request->description
        ]);

        session()->put('user_id', $request['user_id']);
        session()->put('order_id', $request->order_id);
        $customer = User::find($request['user_id']);
        // var_dump($customer);
        // die();
        // $track = Order::where(['id' => $request->order_id, 'user_id' => $request['user_id']])->update(['payment_status' => 'paid']);
        // $udate = $track->update(['payment_status' => 'paid']);
        //   var_dump($track);
        // die();
        // $track->update(['payment_status' => 'paid']);
        $order = Order::where(['id' => $request->order_id, 'user_id' => $request['user_id']])->first();
        $response = $dataxendit->object();
        // var_dump($order->payment_status);
        // die();
        // return $response;

        if (isset($customer) && isset($order)) {
            $response = $dataxendit->object();
            $data = [
                'name' => $customer['f_name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
            ];
            session()->put('data', $data);
            return $response;
            // return response()->json(['message' => 'Payment succeeded'], 200);;
        }

        return response()->json(['errors' => ['code' => 'order-payment', 'message' => 'Data not found']], 403);
    }


    public function payment_callback(Request $request)
    {
        $data_payment = $request->all();
        $payment_status = $data_payment['status'];
        $external_id = intval($data_payment['external_id']);
        $user_id = $data_payment['user_id'];

        // if (isset($order) && $order->callback != null) {
        //     return redirect($order->callback . '&status=success');
        // }

        // if ($request->has('callback')) {
        //     Order::where(['id' => $request->order_id])->update(['callback' => $request['callback']]);
        // }

        $order = new Order();

        $order = Order::where(['id' => $external_id])->first();

        $order->payment_status = strtolower($payment_status);
        DB::beginTransaction();
        $order->save();
        DB::commit();

        
        //  Order::where(['id' => $external_id, 'user_id' => $user_id])->toBase()->update(['payment_status' => $payment_status]);

        // var_dump($track);
        // return response()->json(['message' => 'Payment succeeded'], 200);
        return response()->json($data_payment, 200);
        
    }

    public function success()
    {
        $order = Order::where(['id' => session('order_id'), 'user_id' => session('user_id')])->first();
        // if (isset($order) && $order->callback != null) {
        //     return redirect($order->callback . '&status=success');
        // }
        $order->payment_status = translate('messages.paid');
        try {
            DB::beginTransaction();
            $order->update();

            DB::commit();

            return response()->json([
                'message' => translate('messages.paid'),
                'order_id' => $order->id
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([$e], 403);
        }
        return response()->json(['message' => 'Payment succeeded'], 200);
    }

    public function fail()
    {
        $order = Order::where(['id' => session('order_id'), 'user_id' => session('customer_id')])->first();
        if (isset($order) && $order->callback != null) {
            return redirect($order->callback . '&status=fail');
        }
        return response()->json(['message' => 'Payment failed'], 403);
    }

    public function payment_check(Request $request){
        try {
            $order = Order::where(['id' => $request->order_id])->first();
            // $executed = RateLimiter::attempt(
            //     response()->json($order),
            //     5,
            //     5,
            // );

            // if (!$executed) {
            //     return response()->json(['message' => 'To many request'], 402);
            // }
            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json([
                'errors' => ['code' => 'order_id', 'message' => translate('messages.not_found')]
            ], 404);
        }
        // https://laravel.com/docs/10.x/rate-limiting
        
    }
}
