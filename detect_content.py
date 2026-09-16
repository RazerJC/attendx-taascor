from PIL import Image

im = Image.open('taascor logo.png').convert('RGB')
w, h = im.size
print('Corners:')
print('Top-left:', im.getpixel((0, 0)))
print('Top-right:', im.getpixel((w - 1, 0)))
print('Bottom-left:', im.getpixel((0, h - 1)))
print('Bottom-right:', im.getpixel((w - 1, h - 1)))

# Let's sample a grid or find pixels that are NOT the background color
bg_color = im.getpixel((0, 0))
print('Background color is:', bg_color)

# Find actual content: pixels with significant distance from background color
# Color distance: abs(r - bg_r) + abs(g - bg_g) + abs(b - bg_b) > 20
min_x, min_y, max_x, max_y = w, h, 0, 0

for y in range(h):
    for x in range(w):
        p = im.getpixel((x, y))
        diff = abs(p[0] - bg_color[0]) + abs(p[1] - bg_color[1]) + abs(p[2] - bg_color[2])
        if diff > 30: # significantly different from background
            if x < min_x: min_x = x
            if x > max_x: max_x = x
            if y < min_y: min_y = y
            if y > max_y: max_y = y

print(f'Actual content bounding box: ({min_x}, {min_y}, {max_x}, {max_y})')
print(f'Content size: {max_x - min_x} x {max_y - min_y}')
