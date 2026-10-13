<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(private OrderService $service)
    {
    }

    public function index(): View
    {
        return view('orders.index');
    }

    public function list(): JsonResponse
    {
        return ApiResponse::ok(OrderResource::collection($this->service->list())->resolve());
    }

    public function store(OrderRequest $request): JsonResponse
    {
        $order = $this->service->create($request->validated());

        return ApiResponse::ok((new OrderResource($order))->resolve(), 'Order berhasil ditambahkan.', 201);
    }

    public function update(OrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->service->update($order, $request->validated());

        return ApiResponse::ok((new OrderResource($order))->resolve(), 'Order berhasil diperbarui.');
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->service->delete($order);

        return ApiResponse::ok(null, 'Order berhasil dihapus.');
    }
}
