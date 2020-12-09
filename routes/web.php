<?php

use App\IpAddressNotFoundException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

function isIpAddress(string $ip_address): bool
{
    return !validator([
        "ip" => $ip_address,
    ], [
        "ip" => ["required", "ip"],
    ])->fails();
}

function ipAddressesFromDomain(string $domain)
{
    $process = new Process(['dig', "+short", $domain]);
    $process->run();

    if (!$process->isSuccessful()) {
        throw new ProcessFailedException($process);
    }

    $output_lines = $process->getOutput();
    $output_lines = array_filter(explode("\n", $output_lines), function ($ip) {
        return isIpAddress($ip);
    });


    if (!$output_lines) {
        throw new IpAddressNotFoundException();
    }

    return array_values($output_lines);
}


$router->get('/', function (\Illuminate\Http\Request $request) use ($router) {
    if (!($domain = $request->get("domain"))) {
        return response()->json([
            "message" => "The domain argument is required."
        ], 402);
    }

    $status = 200;
    $message = "successful";

    try {
        $ip_addresses = ipAddressesFromDomain($domain);
    } catch (Exception $exception) {
        $ip_addresses = null;
        $status = 400;
        $message = "Error: ";

        if ($exception instanceof IpAddressNotFoundException) {
            $message .= "IP Address not found.";
        } else if ($exception instanceof ProcessFailedException) {
            $message .= "Failed to dig for IP.";
        }
    }

    return response()->json(
        compact("status", "message", "ip_addresses"),
        $status
    );
});
