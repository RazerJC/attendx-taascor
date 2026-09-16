from PIL import Image, ImageChops

im = Image.open('taascor logo.png')
print('Original size:', im.size, im.mode)

bg = Image.new('RGB', im.size, (255, 255, 255))
diff = ImageChops.difference(im.convert('RGB'), bg)
bbox = diff.getbbox()
print('Bounding box:', bbox)

if bbox:
    # Add a small padding (e.g. 10px) around bbox
    pad = 15
    w, h = im.size
    left = max(0, bbox[0] - pad)
    top = max(0, bbox[1] - pad)
    right = min(w, bbox[2] + pad)
    bottom = min(h, bbox[3] + pad)
    
    cropped = im.crop((left, top, right, bottom))
    print('Cropped size:', cropped.size)
    cropped.save('public/images/taascor-logo.png')
    
    # Also create a transparent version if desirable:
    # Any pixel very close to white becomes transparent
    rgba = cropped.convert('RGBA')
    datas = rgba.getdata()
    newData = []
    for item in datas:
        # If nearly white (r > 240, g > 240, b > 240)
        if item[0] > 240 and item[1] > 240 and item[2] > 240:
            newData.append((255, 255, 255, 0))
        else:
            newData.append(item)
    rgba.putdata(newData)
    rgba.save('public/images/taascor-logo-transparent.png')
    print('Saved cropped and transparent versions successfully!')
