from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

OUT = Path('screenshots')
NAVY = '#07182b'
PANEL = '#0d2946'
WHITE = '#f7f7fb'
MUTED = '#b8c6d8'


def font(size: int, bold: bool = False):
    name = 'DejaVuSans-Bold.ttf' if bold else 'DejaVuSans.ttf'
    return ImageFont.truetype(f'/usr/share/fonts/truetype/dejavu/{name}', size)


def fit(image: Image.Image, size: tuple[int, int]) -> Image.Image:
    target_w, target_h = size
    ratio = max(target_w / image.width, target_h / image.height)
    resized = image.resize((round(image.width * ratio), round(image.height * ratio)), Image.Resampling.LANCZOS)
    left = max(0, (resized.width - target_w) // 2)
    top = max(0, (resized.height - target_h) // 2)
    return resized.crop((left, top, left + target_w, top + target_h))


def framed(canvas, image, box, label):
    draw = ImageDraw.Draw(canvas)
    x, y, w, h = box
    draw.rounded_rectangle((x, y, x + w, y + h), radius=18, fill=PANEL, outline=WHITE, width=2)
    draw.text((x + 18, y + 13), label, fill=WHITE, font=font(22))
    inner = (x + 2, y + 50, w - 4, h - 52)
    canvas.paste(fit(image, (inner[2], inner[3])), (inner[0], inner[1]))


access = Image.open(OUT / 'onboarding-access.png').convert('RGB')
selection = Image.open(OUT / 'onboarding-device-selection.png').convert('RGB')
tutorial = Image.open(OUT / 'onboarding-tutorial.png').convert('RGB')
password = Image.open(OUT / 'onboarding-password-step.png').convert('RGB')

for source, destination in [
    (access, 'onboarding-access.webp'),
    (selection, 'onboarding-device-selection.webp'),
    (tutorial, 'onboarding-tutorial.webp'),
    (password, 'onboarding-password-step.webp'),
]:
    source.save(OUT / destination, 'WEBP', quality=82, method=6)

canvas = Image.new('RGB', (1600, 1380), NAVY)
draw = ImageDraw.Draw(canvas)
draw.text((64, 42), 'Interactive Employee Onboarding — Product Gallery', fill=WHITE, font=font(38, True))
draw.text((64, 93), 'Protected access, adaptive flow selection, guided tutorial and setup instructions', fill=MUTED, font=font(21))

framed(canvas, selection, (64, 142, 1472, 620), 'Device and flow selection')
framed(canvas, access, (64, 806, 700, 510), 'Password-protected access')
framed(canvas, tutorial, (800, 806, 736, 235), 'Guided tutorial')
framed(canvas, password, (800, 1081, 736, 235), 'Example setup instruction')

canvas.save(OUT / 'onboarding-gallery.webp', 'WEBP', quality=82, method=6)

for png in OUT.glob('onboarding-*.png'):
    png.unlink()
