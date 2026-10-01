"""Build and round-trip verify the public book URL QR (OpenCV, no web service)."""
from pathlib import Path
import cv2

URL = "https://kohandezh.com/fa/books/ai-governance/"
output = Path(__file__).resolve().parents[1] / "wp-theme/kohandezh-knowledge/assets/book-qr.png"
matrix = cv2.QRCodeEncoder_create().encode(URL)
matrix = cv2.copyMakeBorder(matrix, 4, 4, 4, 4, cv2.BORDER_CONSTANT, value=255)
image = cv2.resize(matrix, None, fx=8, fy=8, interpolation=cv2.INTER_NEAREST)
decoded, _, _ = cv2.QRCodeDetector().detectAndDecode(image)
if decoded != URL:
    raise SystemExit("QR round-trip failed")
if not cv2.imwrite(str(output), image):
    raise SystemExit("QR write failed")
print(f"Verified QR: {decoded}")
