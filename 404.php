<?php
// 404.php - Trang lỗi 404
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trang không tìm thấy - TKT Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            text-align: center;
            color: white;
        }
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .error-message {
            font-size: 1.5rem;
            margin-bottom: 2rem;
        }
        .btn-home {
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .btn-home:hover {
            background: white;
            color: #667eea;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <div class="error-message">
            <i class="fas fa-exclamation-triangle mb-3"></i><br>
            Trang bạn tìm kiếm không tồn tại
        </div>
        <p class="mb-4">Có thể trang đã được di chuyển hoặc URL không chính xác.</p>
        
        <div class="mb-4">
            <a href="/" class="btn-home me-3">
                <i class="fas fa-home"></i> Về trang chủ
            </a>
            <a href="/admin" class="btn-home">
                <i class="fas fa-user-shield"></i> Admin
            </a>
        </div>
        
        <small class="text-light">
            Mã lỗi: 404 | <?= date('Y-m-d H:i:s') ?>
        </small>
    </div>
</body>
</html>