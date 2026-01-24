<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>E1M1: Hangar</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap');
        
        body, html { 
            margin: 0; 
            padding: 0; 
            width: 100%; 
            height: 100%; 
            background: #000; 
            overflow: hidden;
            font-family: 'Press Start 2P', monospace;
        }
        
        .container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: linear-gradient(180deg, #1a0000 0%, #000 50%, #1a0000 100%);
        }
        
        .title {
            color: #ff0000;
            font-size: 72px;
            text-shadow: 0 0 20px #ff0000, 0 0 40px #ff0000, 0 0 60px #ff0000;
            animation: pulse 2s infinite;
            margin-bottom: 20px;
        }
        
        .subtitle {
            color: #666;
            font-size: 14px;
            margin-bottom: 40px;
        }
        
        .play-btn {
            background: linear-gradient(180deg, #8B0000 0%, #ff0000 50%, #8B0000 100%);
            color: #fff;
            font-family: 'Press Start 2P', monospace;
            font-size: 18px;
            padding: 20px 40px;
            border: 4px solid #ff0000;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 0 20px #ff0000;
            transition: all 0.3s;
            animation: glow 1.5s infinite alternate;
        }
        
        .play-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 0 40px #ff0000;
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            color: #666;
            font-family: 'Press Start 2P', monospace;
            font-size: 12px;
            text-decoration: none;
        }
        
        .back-btn:hover {
            color: #ff0000;
        }
        
        .skulls {
            color: #ff0000;
            font-size: 40px;
            margin: 30px 0;
        }
        
        .hint {
            color: #444;
            font-size: 10px;
            margin-top: 40px;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        
        @keyframes glow {
            from { box-shadow: 0 0 10px #ff0000; }
            to { box-shadow: 0 0 30px #ff0000, 0 0 50px #8B0000; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="{{ route('login') }}" class="back-btn">&lt;&lt; VOLVER AL TRABAJO</a>
        
        <div class="title">DOOM</div>
        <div class="subtitle">- SHAREWARE EDITION -</div>
        
        <div class="skulls">☠️ 🔥 ☠️</div>
        
        <a href="https://dos.zone/doom-dec-1993/" target="_blank" class="play-btn">
            ▶ JUGAR DOOM
        </a>
        
        <div class="hint">Se abrirá en una nueva pestaña</div>
    </div>
</body>
</html>