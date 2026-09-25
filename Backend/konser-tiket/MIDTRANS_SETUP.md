# Midtrans Setup Guide untuk Production

## 1. Dapatkan Credentials Midtrans

### Sandbox (Testing)
1. Daftar di https://account.midtrans.com/register
2. Login ke https://dashboard.sandbox.midtrans.com/
3. Salin Server Key dan Client Key dari Settings → Access Keys

### Production 
1. Upgrade akun ke Production di dashboard
2. Lengkapi verifikasi bisnis
3. Salin Production Server Key dan Client Key

## 2. Konfigurasi Environment Variables

### Untuk Sandbox Testing
```bash
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_IS_3DS=true
MIDTRANS_SANITIZED=true
```

### Untuk Production
```bash
MIDTRANS_SERVER_KEY=Mid-server-xxxxxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=Mid-client-xxxxxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=true
MIDTRANS_IS_3DS=true
MIDTRANS_SANITIZED=true
```

## 3. Setup Webhook URL

Di Midtrans Dashboard:
1. Pergi ke Settings → Configuration
2. Set Payment Notification URL ke: `https://yourdomain.com/api/webhook/midtrans`
3. Set Finish Redirect URL ke: `https://yourdomain.com/customer/dashboard`
4. Set Unfinish Redirect URL ke: `https://yourdomain.com/customer/dashboard`
5. Set Error Redirect URL ke: `https://yourdomain.com/customer/dashboard`

## 4. Testing Checklist

### Sandbox Testing
- [ ] Order creation works
- [ ] Midtrans popup appears
- [ ] Test payment with sandbox cards:
  - Card: 4811 1111 1111 1114
  - CVV: 123, Expiry: 01/25
- [ ] Webhook receives notifications
- [ ] Tickets generated after successful payment
- [ ] Order status updated correctly

### Production Testing
- [ ] Switch to production credentials
- [ ] Test with real payment methods
- [ ] Verify webhook in production environment
- [ ] Monitor logs for any issues

## 5. Security Considerations

- Simpan credentials di environment variables, jangan hardcode
- Gunakan HTTPS untuk webhook URL
- Validasi webhook signature setiap kali
- Log semua transaksi untuk audit trail
- Set up monitoring untuk failed payments

## 6. Common Issues & Solutions

### Midtrans popup tidak muncul
- Cek apakah Client Key sudah benar
- Pastikan Snap.js loaded dengan benar
- Cek console browser untuk error JavaScript

### Webhook tidak diterima
- Pastikan URL webhook bisa diakses dari internet
- Cek firewall settings
- Validasi webhook signature

### Payment gagal
- Cek Server Key format
- Pastikan tidak ada special characters di order data
- Cek Midtrans dashboard untuk error details

## 7. Monitoring & Logs

Semua transaksi Midtrans sudah dilengkapi dengan logging:
- `storage/logs/laravel.log` - General application logs
- Monitor webhook responses
- Track payment success/failure rates

## 8. Support

- Midtrans Documentation: https://docs.midtrans.com/
- Midtrans Support: support@midtrans.com
- Technical Issues: https://github.com/midtrans/midtrans-php