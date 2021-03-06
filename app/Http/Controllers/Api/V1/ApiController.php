<?php

namespace App\Http\Controllers\Api\V1;

use App\Helpers\BitcoinApiHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Helpers\ArrayHelper;

class ApiController extends Controller
{
    private $supported_methods = ['rates', 'convert'];
    private $badRequestData = [
        'status' => 'error',
        'code' => 400,
        'message' => 'Bad request'
    ];

    public function index(Request $request)
    {
        $validator = Validator::make(
            [ 'method' => $request->input('method') ],
            [ 'method' => 'required|in:' . implode(',', $this->supported_methods)]
        );

        if($validator->fails())
            return response()->json($this->badRequestData, 400);


        $method = $request->input('method');
        if(!method_exists($this, $method))
            abort(501);

        $rates = BitcoinApiHelper::getBitcoinBuyRates();
        if(!$rates)
            abort(503);

        return call_user_func_array([$this, $method], [$request, $rates]);
    }

    private function rates(Request $request, $rates)
    {
        if($request->method() != 'GET')
            return response()->json($this->badRequestData, 400);

        $currency = $request->input('currency');
        if($currency)
        {
            $responseCurrencies = explode(',', $currency);
            $rates = array_filter($rates, function($currency) use ($responseCurrencies) {
                return in_array($currency, $responseCurrencies);
            }, ARRAY_FILTER_USE_KEY);

            if(!ArrayHelper::arrayEqualsIgnoreOrder($responseCurrencies, array_keys($rates)))
                return response()->json($responseCurrencies, 400);

        }

        if(!$rates)
            return response()->json($this->badRequestData, 400);

        asort($rates);
        $data = [
            'status' => 'success',
            'code' => 200,
            'data' => $rates
        ];

        return response()->json($data, 200);
    }

    private function convert(Request $request, $rates)
    {
        if($request->method() != 'POST')
            return response()->json($this->badRequestData, 400);

        $crypto = ['BTC'];
        $rules = [
            'method' => 'required',
            'value' => 'required|numeric|min:0.01',
            'currency_from' => 'required',
            'currency_to' => 'required'
        ];

        $from = $request->input('currency_from');
        $to = $request->input('currency_to');
        $fromCryptoToFiat = in_array($from, $crypto);
        if($fromCryptoToFiat)
        {
            $rules['currency_from'] .= '|in:' . implode(',', $crypto);
            $rules['currency_to'] .= '|not-in:' . implode(',', $crypto);
        }
        else
        {
            $rules['currency_to'] .= '|in:' . implode(',', $crypto);
            $rules['currency_from'] .= '|not-in:' . implode(',', $crypto);
        }

        $validator = Validator::make($request->all(), $rules);
        if($validator->fails())
            return response()->json($this->badRequestData, 400);

        $value = $request->input('value');
        $rate = $fromCryptoToFiat ? $rates[$to] : $rates[$from];
        $converted_value = $fromCryptoToFiat ? number_format($value * $rate, 2) : number_format($value / $rate, 10);

        $data = [
            'status' => 'success',
            'code' => 200,
            'data' => [
                    'currency_from' => $from,
                    'currency_to' => $to,
                    'value' => (float)$value,
                    'converted_value' => $converted_value,
                    'rate' => number_format($rate, 2)
                ]
            ];

        return response()->json($data, 200);
    }
}
