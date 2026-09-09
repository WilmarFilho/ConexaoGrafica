<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Renderiza as telas de pedidos autenticado e salva o HTML em storage/app/snapshots
 * para conferência visual sem passar pelo login. Usa o banco configurado no
 * ambiente (rode com DB_CONNECTION=mariadb para bater no banco local de dev).
 */
class OrdersPanelSnapshotTest extends TestCase
{
    public function test_orders_pages_render_for_authenticated_user(): void
    {
        if (! env('HUB_SNAPSHOT')) {
            $this->markTestSkipped('Só roda sob demanda: HUB_SNAPSHOT=1 e banco de dev com pedidos.');
        }

        $user = User::query()->firstOrFail();
        $order = Order::query()->with('items')->firstOrFail();

        $list = $this->actingAs($user)->get('/admin/orders');
        $list->assertOk()->assertSee('Central de Pedidos')->assertSee($order->customer->name);

        $view = $this->actingAs($user)->get('/admin/orders/'.$order->getKey());
        $view->assertOk()->assertSee('Cliente e entrega');

        $exp = $this->actingAs($user)->get('/admin/expedicao');
        $exp->assertOk()->assertSee('Central de Expedição')->assertSee('Gerar etiquetas em lote');

        $prod = $this->actingAs($user)->get('/admin/products');
        $prod->assertOk()->assertSee('Produtos');
        Storage::disk('local')->put('snapshots/products.html', $prod->getContent());

        Storage::disk('local')->put('snapshots/orders-list.html', $list->getContent());
        Storage::disk('local')->put('snapshots/order-view.html', $view->getContent());
        Storage::disk('local')->put('snapshots/expedicao.html', $exp->getContent());
    }
}
