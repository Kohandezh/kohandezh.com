from pathlib import Path
from lxml import etree as E
import zipfile,copy,json,csv,hashlib,re
ROOT=Path('/Users/emperor/Documents/AI/kohandezh.com/IOT-Book')
EDIT=ROOT/'06-Editorial/Chapter-05'
W='http://schemas.openxmlformats.org/wordprocessingml/2006/main';NS={'w':W}
def el(tag,**attrs):return E.Element('{'+W+'}'+tag,**{'{'+W+'}'+k:str(v) for k,v in attrs.items()})
def para(text,style=None,ltr=False):
 p=el('p');pp=el('pPr');p.append(pp)
 if style:pp.append(el('pStyle',val=style))
 pp.append(el('bidi',val='0' if ltr else '1'));pp.append(el('jc',val='left' if ltr else 'right'))
 pp.append(el('spacing',after=100,line=300,lineRule='auto'))
 if style:pp.append(el('keepNext'))
 for segment in re.findall(r'[\x20-\x7E]+|[^\x20-\x7E]+',text):
  r=el('r');rp=el('rPr');rp.append(el('rFonts',ascii='Arial',hAnsi='Arial',cs='Arial'));rp.append(el('sz',val=24));rp.append(el('szCs',val=24))
  if style:rp.append(el('b'));rp.append(el('bCs'))
  latin=ltr or bool(re.search('[A-Za-z0-9]',segment))
  rp.append(el('rtl',val='0' if latin else '1'));r.append(rp);t=el('t');t.set('{http://www.w3.org/XML/1998/namespace}space','preserve');t.text=(segment[:len(segment)-len(segment.lstrip())]+'\u200e'+segment.strip()+'\u200e'+segment[len(segment.rstrip()):]) if latin and not ltr else segment;r.append(t);p.append(r)
 return p

def table(rows,widths=(2500,900,900,900,900)):
 tb=el('tbl');pr=el('tblPr');pr.append(el('tblW',w=sum(widths),type='dxa'));pr.append(el('tblLayout',type='fixed'));pr.append(el('jc',val='center'))
 borders=el('tblBorders')
 for x in ['top','left','bottom','right','insideH','insideV']:borders.append(el(x,val='single',sz=4,color='B8C5D3'))
 pr.append(borders);tb.append(pr);g=el('tblGrid')
 for w in widths:g.append(el('gridCol',w=w))
 tb.append(g)
 for i,row in enumerate(rows):
  tr=el('tr');tp=el('trPr');tp.append(el('cantSplit'))
  if i==0:tp.append(el('tblHeader'))
  tr.append(tp)
  for val,w in zip(row,widths):
   c=el('tc');cp=el('tcPr');cp.append(el('tcW',w=w,type='dxa'));cp.append(el('vAlign',val='center'))
   if i==0:cp.append(el('shd',fill='E7EDF4',val='clear'))
   c.append(cp);p=para(val,ltr=True);p.find('w:pPr/w:jc',NS).set('{'+W+'}val','center');c.append(p);tr.append(c)
  tb.append(tr)
 return tb

canon=json.loads((EDIT/'canonical-source.json').read_text())
rec=sum([json.loads((EDIT/f).read_text()) for f in ['FR1-2.json','FR3-5.json','FR6-7.json']],[])
by={x['id']:x for x in rec};assert len(by)==51
issues=[]
for c in canon:
 x=by[c['id']]
 assert x['clause']==c['clause'],(x['id'],'clause')
 assert [a['id'] for a in x['re']]==[a['id'] for a in c['re']],(x['id'],'RE ids')
 for a,b in zip(x['re'],c['re']):assert a['clause']==b['clause'],(a['id'],'RE clause')
 if x['levels']!=c['levels']:issues.append({'id':x['id'],'agent':x['levels'],'source':c['levels']})
 x['levels']=c['levels']
 # source title cleaned by source extraction; translated headings retain agent output.
 x['title_en']=c['title_en']
 assert x['title_fa'] and x['requirement_fa']
(EDIT/'mapping-discrepancies.json').write_text(json.dumps(issues,ensure_ascii=False,indent=2))
(EDIT/'requirements-reviewed.json').write_text(json.dumps(rec,ensure_ascii=False,indent=2))
frs={1:('کنترل شناسایی و احراز هویت','IAC'),2:('کنترل استفاده','UC'),3:('یکپارچگی سیستم','SI'),4:('محرمانگی داده‌ها','DC'),5:('محدودیت جریان داده‌ها','RDF'),6:('پاسخ به‌موقع به رویدادها','TRE'),7:('دسترس‌پذیری منابع','RA')}
content=[];md=[]
def add(t,style=None,ltr=False):content.append(para(t,style,ltr));md.append(('## ' if style=='Heading2' else '### ' if style=='Heading3' else '# ' if style=='Heading1' else '')+t+'\n')
add('فصل ۵: الزامات امنیتی سیستم و سطوح امنیتی — بر اساس IEC 62443-3-3:2013 و COR1:2014','Heading1')
add('این فصل، راهنمای آموزشی الزامات امنیتی سیستم است. شماره‌ها، عنوان‌ها و نگاشت سطوح از IEC 62443-3-3:2013 گرفته شده و اصلاحیهٔ آوریل ۲۰۱۴ در SR 3.4 اعمال شده است. شرح فارسی هر الزام و ارتقا خلاصهٔ آموزشی است؛ متن کامل هنجاری، یادداشت‌ها و استدلال‌های استاندارد باید هنگام طراحی، قرارداد یا ارزیابی انطباق به منبع اصلی مراجعه و مقابله شوند.')
add('5.1 دامنه، روش خواندن و اصطلاحات','Heading2')
add('این بخش از خانوادهٔ IEC 62443 الزامات امنیتی سیستم کنترل را بیان می‌کند؛ الزامات خاص اجزا موضوع 4-2 و ارزیابی ریسک و تعیین سطح هدف موضوع 3-2 است. شمارهٔ فصل کتاب با شمارهٔ بند استاندارد متفاوت است؛ ارجاع «بند منبع» کنار هر الزام این تفاوت را روشن می‌کند.')
add('SR یعنی الزام سامانه‌ای (System requirement)، RE یعنی ارتقای الزام و FR یعنی الزام بنیادی. هر SR یک الزام پایه و صفر یا چند RE دارد. REها شماره‌گذاری مستقل درون همان SR دارند؛ وجود واژهٔ «ارتقا» به‌معنای اختیاری‌بودن آن در سطحی که انتخاب شده نیست. «باید» الزام، «بهتر است» توصیه و «مجاز است» اجازه را بیان می‌کند. عبارت «قابلیت فراهم کند» را نباید بدون بررسی به دستور بهره‌برداری همیشگی تبدیل کرد. مبنا: بند 3.3 و اختصارات بند 3.2 منبع.')
add('SL-T سطح امنیتی هدف، SL-C سطح قابلیت امنیتی و SL-A سطح امنیتی محقق‌شده است. جدول این فصل به قابلیت سیستم برای هر FR مربوط است؛ نتیجهٔ آن به‌تنهایی سطح محقق‌شدهٔ یک سایت را اثبات نمی‌کند. سطح هدف باید با ارزیابی ریسک تعیین شود و قابلیت و پیکربندی و تدابیر واقعی سپس ارزیابی شوند. مبنا: بند 3 و پیوست A منبع.')
add('SL 0 برای یک FR به‌معنای انتخاب‌نشدن الزام در چارچوب این نگاشت است. توصیف دقیق SLهای 1 تا 4 در ابتدای هر FR آمده است؛ به‌طور کلی سطح 1 با اقدام اتفاقی یا ساده، سطح 2 با ابزار ساده و منابع کم و مهارت عمومی، سطح 3 با ابزار پیچیده و منابع متوسط و مهارت خاص IACS، و سطح 4 با ابزار پیچیده و منابع گسترده و مهارت خاص IACS و انگیزهٔ زیاد مرتبط است. این سطوح امتیاز عمومی بلوغ سازمان یا تضمین مطلق ایمنی نیستند.')
add('5.2 قیود مشترک امنیت سیستم کنترل','Heading2')
add('بند 4.2 منبع بر حفظ عملکردهای حیاتی مرتبط با سلامت، ایمنی، محیط زیست و دسترس‌پذیری تأکید می‌کند. تدابیر امنیتی نباید بدون پشتوانهٔ ارزیابی ریسک به عملکردهای حیاتی IACS با دسترس‌پذیری بالا آسیب بزنند. از دست‌رفتن حفاظت که به پیامدهای سلامت، ایمنی یا محیط زیست منجر شود پذیرفتنی نیست؛ پیاده‌سازی باید خطر از دست‌رفتن کنترل یا دید را نیز بررسی کند.')
add('نمونه‌های مهم: حساب‌های لازم برای عملکرد حیاتی نباید حتی موقتاً قفل شوند؛ احراز هویت و اعمال مجوز نباید شروع عملکرد ایمنی ابزاربندی‌شده (SIF) را متوقف کنند؛ ثبت اعمال اپراتور برای انکارناپذیری نباید تأخیر قابل‌توجه ایجاد کند؛ شکست مرجع گواهی در سیستم با دسترس‌پذیری بالا نباید عملکرد حیاتی را قطع کند. حفاظت مرز ناحیه در حالت fail-close یا جزیره‌ای و رویداد DoS در شبکهٔ کنترل یا SIS نیز باید با حفظ SIF سازگار باشند. مبنا: بند 4.2 منبع.')
add('تدابیر جبرانی باید با راهنمای 3-2 سازگار باشند. برخی قابلیت‌های امنیتی ممکن است از جزء بیرونی تأمین شوند؛ سیستم باید رابط مناسب آن قابلیت را فراهم کند. حداقل امتیاز باید با دانه‌بندی مناسب مجوز و نگاشت انعطاف‌پذیر نقش‌ها قابل اعمال باشد. بنابراین سطح بالاتر همیشه برای هر عملکرد صنعتی انتخاب بهتر نیست و باید با ریسک و تداوم عملکرد سازگار شود. مبنا: بندهای 4.3 و 4.4 منبع.')
add('5.3 الزامات بنیادی، الزامات پایه و ارتقاها','Heading2')
add('در ادامه، هر عنوان با شناسه و بند اصلی آمده است. «خلاصهٔ الزام پایه» و «خلاصهٔ ارتقا» بیان آموزشیِ محتوای منبع هستند. هیچ شمارهٔ SR یا RE جدیدی برای کتاب تعریف نشده است.')
for f in range(1,8):
 name,short=frs[f];add(f'FR {f} — {name} ({short})','Heading2')
 for x in rec:
  if int(x['id'].split()[1].split('.')[0])!=f:continue
  add(x['id']+' — '+x['title_fa'],'Heading3');add(x['title_en']+' | Source clause '+x['clause'],ltr=True)
  add('خلاصهٔ الزام پایه: '+x['requirement_fa'])
  if x['re']:
   for a in x['re']:
    add(a['id']+' — '+a['title_fa']+' (بند منبع '+a['clause']+')');add('خلاصهٔ ارتقا: '+a['summary_fa'])
  else:add('ارتقای الزام: در این SR هیچ RE تعریف نشده است.')
add('5.4 نگاشت SRها و REها به سطوح قابلیت امنیتی','Heading2')
add('جدول‌های 5-1 تا 5-7 بازآرایی آموزشی جدول B.1 منبع‌اند. ستون‌ها از چپ به راست SL 1، SL 2، SL 3 و SL 4 هستند. علامت ✓ یعنی آن ردیف در آن سطح برای FR مربوط انتخاب شده است؛ علامت — یعنی انتخاب نشده است، نه ممنوع‌بودن یا بی‌فایده‌بودن کنترل. برای انتخاب سطح باید همهٔ ردیف‌های علامت‌خوردهٔ همان FR، شامل پایه و REها، بررسی شوند. شناسهٔ کامل هر RE در متن بخش 5.3 آمده است.')
rows_csv=[]
for f in range(1,8):
 add(f'جدول 5-{f} — نگاشت FR {f} ({frs[f][1]})','Heading3');rows=[['SR / RE','SL 1','SL 2','SL 3','SL 4']]
 for x in rec:
  if int(x['id'].split()[1].split('.')[0])!=f:continue
  for id in [x['id']]+[a['id'] for a in x['re']]:
   vals=['✓' if id in x['levels'][str(l)] else '—' for l in range(1,5)];rows.append([id]+vals);rows_csv.append([id,x['clause']]+[1 if id in x['levels'][str(l)] else 0 for l in range(1,5)])
 content.append(table(rows));md.append('| '+' | '.join(rows[0])+' |\n');md.append('|---|---|---|---|---|\n');md.extend(['| '+' | '.join(row)+' |\n' for row in rows[1:]])
add('5.5 اصلاحیهٔ اعمال‌شده: COR1:2014','Heading2')
add('در بند 7.6.4 منبع، نخستین مورد برای SR 3.4 از «SL-C(SI, control system) 1: SR 3.4» به «SL-C(SI, control system) 1: Not selected» تغییر می‌کند. بنابراین SR 3.4 در سطح 1 انتخاب نمی‌شود، در سطح 2 الزام پایه و در سطح‌های 3 و 4 الزام پایه همراه RE 1 انتخاب می‌شوند. ردیف‌های جدول B.1 نیز با همین اصلاح بازسازی شده‌اند؛ جدول 5-3 این فصل نسخهٔ اصلاح‌شده را نشان می‌دهد.')
add('5.6 پیوست‌های واقعی استاندارد و کاربرد آن‌ها','Heading2')
add('پیوست A استاندارد، اطلاعاتی و با عنوان «بحث دربارهٔ بردار SL» است. این پیوست رابطهٔ سطح هدف، قابلیت و سطح محقق‌شده و نمایش چندبعدی سطح امنیتی را توضیح می‌دهد. ترتیب بردار مطابق هفت FR است: IAC، UC، SI، DC، RDF، TRE و RA. سطوح این هفت مؤلفه الزاماً یکسان نیستند و نباید بدون روش مشخص در یک عدد میانگین ادغام شوند.')
add('مثال آموزشی نویسنده: بردار SL-T = (2, 2, 2, 1, 2, 2, 2) تنها نمونه‌ای برای خواندن بردار است، نه توصیهٔ سطح برای یک صنعت یا معماری. مقدار هر مؤلفه باید از ارزیابی ریسک همان ناحیه یا مجرا به دست آید؛ قابلیت سیستم و شواهد اجرای واقعی با آن مقایسه می‌شوند.')
add('پیوست B استاندارد، اطلاعاتی و با عنوان «نگاشت SRها و REها به سطوح FR SL از 1 تا 4» است. جدول B.1 انتخاب پایه‌ها و ارتقاها را نشان می‌دهد و باید همراه متن هنجاری بندهای 5 تا 11 خوانده شود؛ بازآرایی اصلاح‌شدهٔ آن در بخش 5.4 کتاب آمده است. این ویرایش منبع پیوست‌های C، D یا E ندارد؛ پیوست‌های تألیفی کتاب باید با عنوان و منشأ مستقل معرفی شوند.')
add('5.7 روش استفاده در طراحی و ارزیابی','Heading2')
add('مسیر کاربردی پیشنهادی نویسنده: ابتدا محدوده و ناحیه‌ها و مجراها و سطح هدف را از ارزیابی ریسک مشخص کنید؛ سپس برای هر FR ردیف‌های انتخاب‌شده را استخراج کنید؛ محل اجرای قابلیت در سیستم یا تدبیر جبرانی و شواهد آزمون را ثبت کنید؛ پیش از پذیرش، اثر کنترل‌ها بر عملکرد حیاتی و ایمنی را بررسی کنید. این مسیر خلاصهٔ اجرایی کتاب است و جایگزین فرایند کامل 3-2 یا روش ارزیابی انطباق نیست.')
add('برای هر الزام در پروندهٔ پروژه، شناسهٔ SR/RE، بند منبع، سطح هدف FR، جزء یا فرایند مسئول، تنظیم یا تدبیر، روش آزمون، نتیجه و ریسک باقی‌مانده ثبت شود. نباید داشتن گواهی یک جزء را بدون بررسی معماری و پیکربندی به انطباق کل سیستم تعمیم داد.')
add('منابع فصل: IEC 62443-3-3:2013، ویرایش 1.0، بندهای 3 تا 11 و پیوست‌های A و B؛ IEC 62443-3-3:2013/COR1:2014، اصلاحیهٔ آوریل 2014، بند 7.6.4 و جدول B.1. ارجاع‌های این فصل به 3-2 و 4-2 برای تفکیک دامنه است؛ متن کامل نسخه‌های جدید آن‌ها در این مرحله ترجمه نشده است.')

src=ROOT/'01-Manuscript/کتاب-امنیت-سایبری-IACS-پیش‌نویس.docx';out=src.with_name('کتاب-امنیت-سایبری-IACS-فصل۵-اصلاح‌شده.docx');z=zipfile.ZipFile(src);root=E.fromstring(z.read('word/document.xml'));body=root.find('w:body',NS)
def text(p):return ''.join(p.xpath('.//w:t/text()',namespaces=NS))
starts=[i for i,p in enumerate(body) if text(p).startswith('فصل 5:')];start=starts[-1];end=next(i for i in range(start+1,len(body)) if text(body[i]).startswith('فصل 6:'))
before=[E.tostring(p) for p in list(body)[:start]];after=[E.tostring(p) for p in list(body)[end:]]
old_text='\n'.join(text(x) for x in list(body)[start:end]);(EDIT/'chapter05-before.txt').write_text(old_text)
for p in list(body)[start:end]:body.remove(p)
for i,p in enumerate(content):body.insert(start+i,copy.deepcopy(p))
assert before==[E.tostring(p) for p in list(body)[:start]]
assert after==[E.tostring(p) for p in list(body)[start+len(content):]]
xml=E.tostring(root,xml_declaration=True,encoding='UTF-8',standalone=True)
def package(path,document):
 with zipfile.ZipFile(path,'w',zipfile.ZIP_DEFLATED) as zz:
  for item in z.infolist():zz.writestr(copy.copy(item),document if item.filename=='word/document.xml' else z.read(item.filename))
package(out,xml)
previewroot=copy.deepcopy(root);pb=previewroot.find('w:body',NS);sect=copy.deepcopy(pb.find('w:sectPr',NS))
for p in list(pb):pb.remove(p)
for p in content:pb.append(copy.deepcopy(p))
if sect is not None:pb.append(sect)
package(Path('/tmp/iot-step1/chapter05-preview.docx'),E.tostring(previewroot,xml_declaration=True,encoding='UTF-8',standalone=True))
(EDIT/'فصل۵-اصلاح‌شده.md').write_text('\n'.join(md))
with (EDIT/'SL-mapping-corrected.csv').open('w',newline='') as f:
 w=csv.writer(f);w.writerow(['id','source_SR_clause','SL1','SL2','SL3','SL4']);w.writerows(rows_csv)
assert len(rows_csv)==100
status={'SR_count':len(rec),'RE_count':sum(len(x['re']) for x in rec),'mapping_rows':len(rows_csv),'corr1_applied':True,'outside_chapter_xml_unchanged':True,'original_sha256':hashlib.sha256(src.read_bytes()).hexdigest(),'output':str(out),'source_mapping_discrepancies':issues}
(EDIT/'verification.json').write_text(json.dumps(status,ensure_ascii=False,indent=2))
print(json.dumps(status,ensure_ascii=False))
