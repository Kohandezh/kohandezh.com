from pathlib import Path
from lxml import etree as E
from PIL import Image
import json,zipfile,copy,re,hashlib
R=Path(__file__).resolve().parents[2];OUT=R/'01-Manuscript/کتاب-امنیت-سایبری-IACS-با-تصاویر-فارسی.docx';SRC=R/'01-Manuscript/کتاب-امنیت-سایبری-IACS-chapter-05-revised.docx'
W='http://schemas.openxmlformats.org/wordprocessingml/2006/main';REL='http://schemas.openxmlformats.org/package/2006/relationships';RN='http://schemas.openxmlformats.org/officeDocument/2006/relationships';WP='http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';A='http://schemas.openxmlformats.org/drawingml/2006/main';PIC='http://schemas.openxmlformats.org/drawingml/2006/picture';N={'w':W,'wp':WP,'a':A,'r':RN}
def el(t,**attrs):return E.Element('{'+W+'}'+t,**{'{'+W+'}'+k:str(v) for k,v in attrs.items()})
def text(p):return ''.join(p.xpath('.//w:t/text()',namespaces=N))
def digits(s):return str(s).translate(str.maketrans('0123456789','۰۱۲۳۴۵۶۷۸۹'))
def num(m):return '\u200e'+digits(str(m['chapter'])+'–'+str(int(m['id'].split('-')[-1])))+'\u200e'
def para(t,size=22,bold=False,center=False,keep=False,style=None):
 p=el('p');pr=el('pPr');p.append(pr)
 if style:pr.append(el('pStyle',val=style))
 pr.append(el('bidi',val=1));pr.append(el('jc',val='center' if center else 'right'));pr.append(el('spacing',after=100,line=280,lineRule='auto'))
 if keep:pr.append(el('keepNext'))
 for segment in re.findall(r'[\x20-\x7E]+|[^\x20-\x7E]+',t):
  r=el('r');rp=el('rPr');r.append(rp);rp.append(el('rFonts',ascii='Tahoma',hAnsi='Tahoma',cs='Tahoma'));rp.append(el('sz',val=size));rp.append(el('szCs',val=size));latin=bool(re.search('[A-Za-z0-9]',segment));rp.append(el('rtl',val=0 if latin else 1))
  if bold:rp.append(el('b'));rp.append(el('bCs'))
  tt=el('t');tt.set('{http://www.w3.org/XML/1998/namespace}space','preserve');tt.text=segment;r.append(tt);p.append(r)
 return p
z=zipfile.ZipFile(SRC);doc=E.fromstring(z.read('word/document.xml'));body=doc.find('w:body',N);rels=E.fromstring(z.read('word/_rels/document.xml.rels'));ct=E.fromstring(z.read('[Content_Types].xml'));original=list(body)
# actual body chapter starts, excluding front outline
starts={}
for i,p in enumerate(original):
 m=re.match(r'^فصل\s+([0-9۰-۹]+)\s*:',text(p))
 if m:starts[int(m[1].translate(str.maketrans('۰۱۲۳۴۵۶۷۸۹','0123456789')))]=i
assert set(range(1,11)).issubset(starts)
ranges={k:(v,starts[k+1] if k<10 else len(original)-1) for k,v in starts.items()}
manifest=sorted(json.loads((R/'07-Figures/manifest.json').read_text()),key=lambda m:m['id']);plans={};replace={};modified=[];media={};placement=[]
maxrid=max([int(x.get('Id')[3:]) for x in rels if re.match(r'rId\d+$',x.get('Id',''))]+[0]);maxdoc=max([int(x.get('id')) for x in doc.findall('.//{'+WP+'}docPr')]+[0])
if not any(x.get('Extension')=='png' for x in ct):E.SubElement(ct,'{http://schemas.openxmlformats.org/package/2006/content-types}Default',Extension='png',ContentType='image/png')
def imagepara(m,rid,did):
 p=el('p');pp=el('pPr');p.append(pp);pp.append(el('jc',val='center'));pp.append(el('keepNext'));pp.append(el('spacing',after=80));r=el('r');p.append(r);d=el('drawing');r.append(d)
 # image width constrained to smallest relevant source section (6.4in); tall diagrams remain legible
 width=5852160;im=Image.open(R/m['png']);height=int(width*im.height/im.width)
 inline=E.SubElement(d,'{'+WP+'}inline',distT='0',distB='0',distL='0',distR='0');E.SubElement(inline,'{'+WP+'}extent',cx=str(width),cy=str(height));E.SubElement(inline,'{'+WP+'}effectExtent',l='0',t='0',r='0',b='0');E.SubElement(inline,'{'+WP+'}docPr',id=str(did),name=m['id'],descr=m['title']);lock=E.SubElement(inline,'{'+WP+'}cNvGraphicFramePr');E.SubElement(lock,'{'+A+'}graphicFrameLocks',noChangeAspect='1');graphic=E.SubElement(inline,'{'+A+'}graphic');gd=E.SubElement(graphic,'{'+A+'}graphicData',uri=PIC);pic=E.SubElement(gd,'{'+PIC+'}pic');nv=E.SubElement(pic,'{'+PIC+'}nvPicPr');E.SubElement(nv,'{'+PIC+'}cNvPr',id='0',name=m['id']+'.png',descr=m['title']);E.SubElement(nv,'{'+PIC+'}cNvPicPr');bf=E.SubElement(pic,'{'+PIC+'}blipFill');E.SubElement(bf,'{'+A+'}blip',{'{'+RN+'}embed':rid});st=E.SubElement(bf,'{'+A+'}stretch');E.SubElement(st,'{'+A+'}fillRect');sp=E.SubElement(pic,'{'+PIC+'}spPr');xf=E.SubElement(sp,'{'+A+'}xfrm');E.SubElement(xf,'{'+A+'}off',x='0',y='0');E.SubElement(xf,'{'+A+'}ext',cx=str(width),cy=str(height));geom=E.SubElement(sp,'{'+A+'}prstGeom',prst='rect');E.SubElement(geom,'{'+A+'}avLst');return p

def figure(m):
 global maxrid,maxdoc
 maxrid+=1;maxdoc+=1;rid='rId'+str(maxrid);target='media/'+m['id']+'-fa.png';E.SubElement(rels,'{'+REL+'}Relationship',Id=rid,Type=RN+'/image',Target=target);media['word/'+target]=(R/m['png']).read_bytes()
 caption=para('شکل '+num(m)+' — '+m['title'],22,True,True,True)
 if m['kind']=='author':source='طرح آموزشی نویسنده؛ مبنا: '+m['source']+'؛ این شکل، شکل اصلی استاندارد نیست.'
 elif m['kind']=='table-adaptation':source='بازطراحی آموزشی از جدول ۱ در '+m['source']+'؛ صفحهٔ چاپی '+digits(m['printed_page'])+'، صفحهٔ PDF '+digits(m['pdf_page'])+'.'
 else:source='بازطراحی آموزشی و ساده‌شده بر پایهٔ '+m['source']+'؛ شکل منبع '+digits(m['source_figure'])+'، صفحهٔ چاپی '+digits(m['printed_page'])+'، صفحهٔ PDF '+digits(m['pdf_page'])+'.'
 return [imagepara(m,rid,maxdoc),caption,para(source,19,False,True)]
# Add figures at documented paragraph anchors. Existing figure-description placeholders replaced by actual image blocks.
for m in manifest:
 ch=m['chapter'];start,end=ranges[ch];indices=list(range(start+1,end));anchor=m['anchor'];idx=None;isplaceholder=False
 if ch==2 and m['source_figure']>=17:
  idx=end-1
 else:
  if ch==4 and m['id']=='fig-04-02':anchor='این بخش از IEC 62443 یک برنامه امنیتی را'
  if anchor and anchor.startswith('شکل '):
   idx=next((i for i in indices if text(original[i]).startswith(anchor)),None);isplaceholder=idx is not None
  else:idx=next((i for i in indices if anchor and anchor in text(original[i])),None)
  assert idx is not None,(m['id'],anchor)
 block=figure(m)
 if isplaceholder:replace[idx]=block
 else:
  if ch==2 and m['source_figure']>=17:
   if m['source_figure']==17:
    plans.setdefault(idx,[]).append(para('۶.۵ تصاویر تکمیلی مدل ناحیه و مجرا',26,True,False,True,'Heading2'))
    plans[idx].append(para('شکل‌های زیر مدل‌های ناحیه و مجرا را تکمیل می‌کنند. مرزها نمونه‌اند و باید بر پایهٔ ارزیابی ریسک و سیاست سازمان تعیین شوند.',22))
   plans.setdefault(idx,[]).append(para('شکل '+num(m)+'، '+m['title']+' را نشان می‌دهد.',22,False,False,True))
  else:plans.setdefault(idx,[]).append(para('برای نمایش این مفهوم، به شکل '+num(m)+' مراجعه کنید.',22,False,False,True))
  plans.setdefault(idx,[]).extend(block)
 placement.append({'id':m['id'],'chapter':ch,'anchor_before':text(original[idx]),'original_body_index':idx,'replaced_placeholder':isplaceholder})
# Update only figure citations, and two directly associated misleading labels.
for ch,(start,end) in ranges.items():
 if ch>7:continue
 for i in range(start,end):
  if i in replace:continue
  p=original[i];t=text(p);new=t
  if ch in [1,2]:
   def ref(mt):
    n=int(mt[1]);id='fig-01-01' if n==1 else f'fig-02-{n-1:02}';mm=next((a for a in manifest if a['id']==id),None);return 'شکل '+num(mm) if mm else mt[0]
   new=re.sub(r'شکل\s+(\d+)(?!\d)(?:\s*\(Figure\s+\d+\))?',ref,new)
  if ch==3 and 'رویکرد چرخه حیات در شکل 1' in new:
   mm=next(a for a in manifest if a['id']=='fig-02-07');new='رویکرد چرخهٔ حیات در شکل '+num(mm)+' نمایش داده شده است؛ شکل '+num(next(a for a in manifest if a['id']=='fig-03-01'))+' عناصر برنامهٔ مدیریت امنیت را نشان می‌دهد.'
  if ch==4:new=new.replace('ML4: بهینه‌سازی شده (Optimizing)','ML4: در حال بهبود (Improving)؛ مطابق جدول 1 ویرایش 2015')
  if ch==7 and 'این فرآیند در شکل 1' in new:
   new=new.replace('این فرآیند در شکل 1 به تصویر کشیده شده است.','نمای آموزشی این فرآیند در شکل '+num(next(a for a in manifest if a['id']=='fig-07-02'))+' آمده است؛ مدل وضعیت وصلهٔ منبع در شکل '+num(next(a for a in manifest if a['id']=='fig-07-01'))+' نمایش داده می‌شود.')
  if new!=t:
   # Plain paragraph texts in this manuscript; preserve paragraph properties and replace only textual runs.
   pp=p.find('w:pPr',N);fresh=para(new,size=24);fp=fresh.find('w:pPr',N);fresh.remove(fp)
   if pp is not None:fresh.insert(0,copy.deepcopy(pp))
   original[i]=fresh;modified.append({'index':i,'before':t,'after':new})
# Commit ordered source body plus insertions.
for child in list(body):body.remove(child)
for i,p in enumerate(original):
 if i in replace:
  for a in replace[i]:body.append(copy.deepcopy(a))
 else:body.append(copy.deepcopy(p))
 for a in plans.get(i,[]):body.append(copy.deepcopy(a))
# preserve all ZIP parts except required document/image relationships/content-types
changed={'word/document.xml':E.tostring(doc,xml_declaration=True,encoding='UTF-8',standalone=True),'word/_rels/document.xml.rels':E.tostring(rels,xml_declaration=True,encoding='UTF-8',standalone=True),'[Content_Types].xml':E.tostring(ct,xml_declaration=True,encoding='UTF-8',standalone=True)}
with zipfile.ZipFile(OUT,'w',zipfile.ZIP_DEFLATED) as q:
 for item in z.infolist():q.writestr(copy.copy(item),changed.get(item.filename,z.read(item.filename)))
 for name,data in media.items():q.writestr(name,data)
with zipfile.ZipFile(OUT) as q:
 assert q.testzip() is None
 assert all(z.read(n)==q.read(n) for n in z.namelist() if n not in changed)
# Isolated proof document: each image with its caption and source, one figure per page.
proof=E.fromstring(z.read('word/document.xml'));pb=proof.find('w:body',N);sect=copy.deepcopy(pb.find('w:sectPr',N))
for child in list(pb):pb.remove(child)
# derive proof images by docPr names from final doc; use same rels.
blocks={}
for i,p in enumerate(body):
 dp=p.find('.//{'+WP+'}docPr')
 if dp is not None and dp.get('name') in [m['id'] for m in manifest]:blocks[dp.get('name')]=[copy.deepcopy(x) for x in list(body)[i:i+3]]
for j,m in enumerate(manifest):
 if j:
  b=el('p');rr=el('r');b.append(rr);rr.append(el('br',type='page'));pb.append(b)
 pb.append(para(m['id']+' — '+m['title'],24,True,False,True))
 for a in blocks[m['id']]:pb.append(a)
pb.append(sect)
with zipfile.ZipFile('/tmp/iot-figures/figures-proof.docx','w',zipfile.ZIP_DEFLATED) as q:
 for item in z.infolist():q.writestr(copy.copy(item),E.tostring(proof,xml_declaration=True,encoding='UTF-8',standalone=True) if item.filename=='word/document.xml' else changed.get(item.filename,z.read(item.filename)))
 for name,data in media.items():q.writestr(name,data)
report={'source_sha256':hashlib.sha256(SRC.read_bytes()).hexdigest(),'output':str(OUT),'figure_count':len(manifest),'embedded_images':len(media),'placements':placement,'figure_reference_edits':modified,'package_integrity':'passed','unrelated_package_parts_unchanged':True,'source_preserved':True}
(R/'06-Editorial/Figures/insertion-verification.json').write_text(json.dumps(report,ensure_ascii=False,indent=2));print(json.dumps({'output':str(OUT),'figures':len(manifest),'reference_edits':len(modified)},ensure_ascii=False))
