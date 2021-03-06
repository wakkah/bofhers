<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Api as Telegram;
use Telegram\Bot\FileUpload\InputFile;

use \App\Models\Telegram as Tel;
use \App\Models\TelegramCanal as Canal;
use \App\Models\Quote as Quote;

class TelegramController extends Controller
{
    protected $telegram;

    public function __construct()
    {
        //$this->telegram = new Api(env('TELEGRAM_BOT_TOKEN'));
        $this->telegram = new Telegram(
            env('TELEGRAM_BOT_TOKEN')
        );
    }

    public function getMe()
    {
        $response = $this->telegram->getMe();
        return $response;
    }

    public function setWebHook()
    {
        $url = env('TELEGRAM_WEBHOOK_URL') . env('TELEGRAM_WEBHOOK_KEY') . '/webhook';
        $response = $this->telegram->setWebhook(['url' => $url]);

        return $response == true ? "ok" : dd($response);
    }

    public function removeWebHook()
    {
        $response = $this->telegram->removeWebhook();
        return $response == true ? "removed" : dd($response);
    }


    public function handleRequest(Request $request)
    {
        $this->chat_id = isset($request['message']['chat']['id']) ? $request['message']['chat']['id'] : 0;
        $this->username = isset($request['message']['from']['username']) ? $request['message']['from']['username'] : 'no-username';
        $this->first_name = isset($request['message']['from']['first_name']) ? $request['message']['from']['first_name'] : 'no-first-name';
        $this->last_name = isset($request['message']['from']['last_name']) ? $request['message']['from']['last_name'] : 'no-last-name';
        $this->telegram_user_id = isset($request['message']['from']['id']) ? $request['message']['from']['id'] : 'no-telegram-id';
        $this->text = isset($request['message']['text']) ? $request['message']['text']: 'no-text';
        $this->message_id = isset($request['message']['message_id']) ? $request['message']['message_id']: '';

        /* $this->chat_id = $request['message']['chat']['id']; */
        /* $this->username = $request['message']['from']['username']; */
        /* $this->text = $request['message']['text']; */

        $data = [
            'chat_id'=>$this->chat_id,
            'nick'=>$this->username,
            'first_name'=>$this->first_name,
            'last_name'=>$this->last_name,
            'telegram_user_id'=>$this->telegram_user_id,
            'text'=>$this->text,
            'request'=>$request
        ];
        $tel = new Tel($data);
        $tel->save();

        // Allow commands only in oficial channels (not privates)
        $allowed_channels = Canal::where('active', 1)->pluck('chat_id')->toArray();
        if (in_array($this->chat_id, $allowed_channels)) {

            switch ($this->text) {
                case '/menutifu':
                    $this->sendMessage('Aquí debería ir el menú con los comandos disponibles');
                    //$this->showMenu();
                    break;
                case '!canal':
                    $canal = Canal::where('chat_id', $this->chat_id)->first();
                    $this->sendMessage($canal->description);
                    break;
                case '!chatid':
                    $this->sendMessage($this->chat_id);
                    break;
                case '!web':
                    $canal = Canal::where('chat_id', $this->chat_id)->first();
                    $this->sendMessage($canal->web);
                    break;
                case '!anclado':
                    $this->sendMessage('¡El que tengo aquí colgado! 🍆');
                    break;
                case '!repo':
                    $this->sendMessage('https://github.com/oscarmlage/bofhers');
                    break;
                // Save new quotes
                case ( preg_match( '/!addquote.*/', $this->text ) ? true : false ):
                    if(trim(ltrim($this->text, '!addquote')) == '') {
                        $this->sendMessage('❌ Pezqueñines no, gracias... ¡hay que dejarlos crecer! 🤷');
                        break;
                    }
                    $data = [
                        'chat_id'=>$this->chat_id,
                        'nick'=>$this->username,
                        'first_name'=>$this->first_name,
                        'last_name'=>$this->last_name,
                        'telegram_user_id'=>$this->telegram_user_id,
                        'quote'=>trim(ltrim($this->text, '!addquote')),
                        'active'=>0,
                    ];
                    $quote = new Quote($data);
                    $quote->save();
                    $this->sendMessage('✅ Quote agregado... ¡y lo llevo aquí colgado!');
                    //$this->showMenu();
                    break;
                // Random quote
                case '!quote':
                    $quote = Quote::where('chat_id', $this->chat_id)->where('active', 1)->orderByRaw("RAND()")->limit(1)->first();
                    $quote->active = -1;
                    $quote->save();
                    if(Quote::where('active', 1)->count() == 0) {
                        Quote::where('active', '=', -1)->update(array('active' => 1));
                    }
                    $this->sendMessage($quote->quote);
                    break;
                // Pesao de las AMI
                case ( (preg_match( '/AMI.*/', $this->text ) ? true : false) && $this->telegram_user_id=='181121900'):
                    $resource = fopen(public_path('/quotes/fifu.png'), 'r');
                    $filename = 'fifu.png';
                    $this->telegram->sendPhoto([
                        'chat_id' => $this->chat_id,
                        'photo' => InputFile::create($resource, $filename),
                        'caption' => 'Pesao, más que pesao, que nadie NADIE quiere tus 1star AMIs.',
                        'reply_to_message_id' => $this->message_id
                    ]);
                    break;
                // COVID COVAD
                case ( preg_match( '/covid$/i', $this->text ) ? true : false ):
                    $this->sendMessage('COVAD! Cada día te quiero mad covid covid.... 🎼🎵🎼🎵🎶');
                    break;
                case '!help':
                    $this->sendMessage('De momento sólo atiendo a: <code>!quote</code>, <code>!addquote texto</code>, <code>!anclado</code>, <code>!repo</code> y <code>!help</code>. De 8h. a 2h.', true);
                    break;
                default:
            }
        }
    }

    public function random(Request $request)
    {
        dd(Quote::where('active', -1)->count());
    }

    public function showMenu($info = null)
    {
        $message = '';
        if ($info) {
            $message .= $info . chr(10);
        }
        $message .= '/menu' . chr(10);
        $this->sendMessage($message);
    }

    protected function sendMessage($message, $parse_html = false)
    {
        $data = [
            'chat_id' => $this->chat_id,
            'text' => $message,
        ];
        if ($parse_html) $data['parse_mode'] = 'HTML';
        $this->telegram->sendMessage($data);
    }

}
