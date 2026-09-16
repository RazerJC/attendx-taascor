from PIL import Image

im = Image.open('taascor logo.png').convert('RGBA')

# Content bbox is roughly (512, 348, 1315, 523)
# Let's crop with a nice 8px padding
pad = 8
left = max(0, 512 - pad)
top = max(0, 348 - pad)
right = min(im.width, 1315 + pad)
bottom = min(im.height, 523 + pad)

cropped = im.crop((left, top, right, bottom))
print('Cropped logo size:', cropped.size)

# Save high-res trimmed logo with clean white bg
rgb_clean = Image.new('RGB', cropped.size, (255, 255, 255))
rgb_clean.paste(cropped, mask=None)
rgb_clean.save('public/images/taascor-logo.png', quality=95)

# Also create transparent version
trans = cropped.copy()
pixels = trans.load()
w, h = trans.size

for y in range(h):
    for x in range(w):
        r, g, b, a = pixels[x, y]
        # If it's the near-white background
        if r >= 242 and g >= 242 and b >= 242:
            pixels[x, y] = (255, 255, 255, 0)

trans.save('public/images/taascor-logo-transparent.png')

# Extract just the hexagon icon (left part of content: x from 512 to ~675, y from 348 to 523)
# Let's find right edge of hexagon
# Hexagon is around width ~160px from left of content
hex_crop = trans.crop((0, 0, 180, h))
hex_crop.save('public/images/taascor-icon.png')

print('Generated public/images/taascor-logo.png (tight crop)')
print('Generated public/images/taascor-logo-transparent.png (transparent)')
print('Generated public/images/taascor-icon.png (hexagon mark)')
