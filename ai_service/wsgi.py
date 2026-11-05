"""
Swastya Setu – Production WSGI Entry Point for Flask AI Service
Supports Waitress (Windows/Cross-platform) and Gunicorn (Linux/Container)
"""

import os
import sys

# Ensure ai_service directory is in sys.path
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from app import app, load_artifacts

# Pre-load artifacts before accepting incoming traffic
load_artifacts()

if __name__ == "__main__":
    port = int(os.environ.get("PORT", 5000))
    host = os.environ.get("HOST", "127.0.0.1")
    
    try:
        from waitress import serve
        print(f"[WSGI] Serving Swastya Setu AI Microservice on http://{host}:{port} with Waitress production server...")
        serve(app, host=host, port=port, threads=4)
    except ImportError:
        print(f"[WSGI] Waitress not installed; starting standard WSGI on http://{host}:{port}...")
        app.run(host=host, port=port, debug=False)

