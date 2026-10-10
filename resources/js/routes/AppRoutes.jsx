import { lazy, Suspense } from 'react';
import { Route, Routes } from 'react-router-dom';
import AdminRoute from '../components/AdminRoute';
import LoadingSpinner from '../components/LoadingSpinner';
import ProtectedRoute from '../components/ProtectedRoute';
import StoreLayout from '../layouts/StoreLayout';
import AccountLayout from '../pages/account/AccountLayout';
import Addresses from '../pages/account/Addresses';
import Orders from '../pages/account/Orders';
import Profile from '../pages/account/Profile';
import Cart from '../pages/Cart';
import Checkout from '../pages/Checkout';
import Home from '../pages/Home';
import Login from '../pages/Login';
import NotFound from '../pages/NotFound';
import OrderDetails from '../pages/OrderDetails';
import ProductDetails from '../pages/ProductDetails';
import Register from '../pages/Register';
import Shop from '../pages/Shop';

// The admin area is a separate chunk, so shoppers never download it.
const AdminLayout = lazy(() => import('../layouts/AdminLayout'));
const AdminDashboard = lazy(() => import('../pages/admin/Dashboard'));
const AdminProducts = lazy(() => import('../pages/admin/Products'));
const AdminProductForm = lazy(() => import('../pages/admin/ProductForm'));
const AdminCategories = lazy(() => import('../pages/admin/Categories'));
const AdminInventory = lazy(() => import('../pages/admin/Inventory'));
const AdminOrders = lazy(() => import('../pages/admin/Orders'));
const AdminOrderDetail = lazy(() => import('../pages/admin/OrderDetail'));
const AdminCustomers = lazy(() => import('../pages/admin/Customers'));
const AdminCustomerDetail = lazy(() => import('../pages/admin/CustomerDetail'));
const AdminReports = lazy(() => import('../pages/admin/Reports'));
const AdminSettings = lazy(() => import('../pages/admin/Settings'));

export default function AppRoutes() {
    return (
        <Routes>
            <Route element={<StoreLayout />}>
                <Route index element={<Home />} />
                <Route path="shop" element={<Shop />} />
                <Route path="category/:slug" element={<Shop />} />
                <Route path="product/:slug" element={<ProductDetails />} />
                <Route path="login" element={<Login />} />
                <Route path="register" element={<Register />} />

                <Route element={<ProtectedRoute />}>
                    <Route path="cart" element={<Cart />} />
                    <Route path="checkout" element={<Checkout />} />
                    <Route path="orders/:orderNumber" element={<OrderDetails />} />
                    <Route path="account" element={<AccountLayout />}>
                        <Route index element={<Profile />} />
                        <Route path="orders" element={<Orders />} />
                        <Route path="addresses" element={<Addresses />} />
                    </Route>
                </Route>

                <Route path="*" element={<NotFound />} />
            </Route>
            <Route element={<AdminRoute />}>
                <Route
                    path="admin"
                    element={
                        <Suspense fallback={<LoadingSpinner className="min-h-screen" />}>
                            <AdminLayout />
                        </Suspense>
                    }
                >
                    <Route index element={<AdminDashboard />} />
                    <Route path="orders" element={<AdminOrders />} />
                    <Route path="orders/:orderNumber" element={<AdminOrderDetail />} />
                    <Route path="products" element={<AdminProducts />} />
                    <Route path="products/new" element={<AdminProductForm />} />
                    <Route path="products/:slug/edit" element={<AdminProductForm />} />
                    <Route path="categories" element={<AdminCategories />} />
                    <Route path="inventory" element={<AdminInventory />} />
                    <Route path="customers" element={<AdminCustomers />} />
                    <Route path="customers/:id" element={<AdminCustomerDetail />} />
                    <Route path="reports" element={<AdminReports />} />
                    <Route path="settings" element={<AdminSettings />} />
                </Route>
            </Route>
        </Routes>
    );
}
