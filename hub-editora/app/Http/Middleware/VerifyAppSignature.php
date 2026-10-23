<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chamadas do site (plugin conexao-app) ao Hub em nome do leitor logado.
 * Assinatura = hex(HMAC-SHA256("{timestamp}\n{MÉTODO}\n{caminho}\nemail={email}", HUB_APP_SECRET)),
 * e-mail em minúsculas e sem codificação de URL; janela de 5 minutos.
 */
class VerifyAppSignature
{
    public const WINDOW_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('hub.app.secret');
        if (strlen($secret) < 32) {
            return response()->json(['codigo' => 'indisponivel', 'mensagem' => 'Integração com o app não configurada.'], 503);
        }

        $timestamp = (string) $request->header('X-Conexao-Timestamp', '');
        $signature = strtolower((string) $request->header('X-Conexao-Signature', ''));

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::WINDOW_SECONDS || $signature === '') {
            return response()->json(['codigo' => 'nao_autenticado', 'mensagem' => 'Assinatura ausente ou expirada.'], 401);
        }

        $expected = self::sign($timestamp, $request->method(), '/'.$request->path(), (string) $request->query('email', ''), $secret);
        if (! hash_equals($expected, $signature)) {
            return response()->json(['codigo' => 'nao_autenticado', 'mensagem' => 'Assinatura inválida.'], 401);
        }

        return $next($request);
    }

    public static function sign(string $timestamp, string $method, string $path, string $email, string $secret): string
    {
        return hash_hmac('sha256', $timestamp."\n".strtoupper($method)."\n".$path."\nemail=".mb_strtolower(trim($email)), $secret);
    }
}
