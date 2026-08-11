# -*- coding: utf-8 -*-
"""Generate per-language llms.txt files from one structured source.

WHY: only English and Persian had one, so an answer engine reading in German,
Japanese, Arabic … had no machine-readable summary in the language it was
answering in. The facts are identical in all nine; only the prose differs, so
they are generated from a single table rather than hand-written nine times —
that is what keeps them from drifting apart as the CV changes.
"""
import io, os

LANGS = ["ar", "de", "es", "fr", "tr", "zh", "ja"]
PAGE = {"ar":"ar.html","de":"de.html","es":"es.html","fr":"fr.html",
        "tr":"tr.html","zh":"zh.html","ja":"ja.html"}

T = {
"ar": dict(
 name="محمد علي كهن‌دژ",
 blurb="محمد علي كهن‌دژ حاصل على درجة الدكتوراه في إدارة تقنية المعلومات، والرئيس التنفيذي لشركة كهن سيستم فردا (KSF)، وهي شركة لتقنية المعلومات المؤسسية والذكاء الاصطناعي مقرها طهران، إيران. يعمل مهنيًا منذ عام ٢٠٠٧ في منتجات الذكاء الاصطناعي، والبنية التحتية المؤسسية لتقنية المعلومات، والمحاكاة الافتراضية، والنسخ الاحتياطي والتعافي من الكوارث، والأمن السيبراني.",
 certs="يحمل أكثر من ١٠ شهادات مهنية (منها VMware VCP-DCV، وEC-Council CEH/CHFI، وHPE ATP، وGIAC/SANS SEC504/SEC542)، ويعمل من TechElite في مركز نمو تقنيات النخبة (مسير ٢١) بحديقة برديس التقنية، طهران، إيران.",
 h_id="الهوية", h_srv="الخدمات", h_pg="الصفحات", h_notes="ملاحظات لمساعدي الذكاء الاصطناعي وبرامج الزحف",
 f_name="الاسم الكامل", f_title="المسمّى", f_company="الشركة", f_edu="التعليم", f_contact="التواصل",
 v_title="الرئيس التنفيذي وخبير البنية التحتية والأمن في تقنية المعلومات",
 v_edu="دكتوراه في إدارة تقنية المعلومات (دكتوراه ثانية قيد الإنجاز)؛ ماجستير في إدارة تقنية المعلومات / الأعمال الإلكترونية، جامعة طهران",
 s1="الذكاء الاصطناعي والأتمتة الذكية", s1d="منتجات الذكاء الاصطناعي ومنصات الوكلاء المؤسسية والأتمتة",
 s2="البنية التحتية والمحاكاة الافتراضية", s2d="VMware vSphere، وتصميم مراكز البيانات، وWindows Server وActive Directory، وKubernetes، وDocker",
 s3="النسخ الاحتياطي والتعافي من الكوارث", s3d="Veeam، وArctera Backup Exec، وVeritas NetBackup",
 s4="الأمن السيبراني والأدلة الجنائية الرقمية", s4d="اختبار الاختراق، والتحليل الجنائي للحوادث، وتدقيق الأمن",
 p_home="الصفحة الرئيسية / السيرة الذاتية (بالعربية)", p_home_d="الملف الكامل وأبرز الأعمال والشهادات وآراء العملاء",
 p_certs="أرشيف الشهادات", p_certs_d="السجل الكامل للشهادات المهنية",
 p_blog="المدونة", p_blog_d="مقالات عن الذكاء الاصطناعي والمسار المهني والتقنية — مترجمة بالكامل إلى تسع لغات",
 p_port="معرض الأعمال", p_port_d="مشاريع مختارة",
 p_other="لغات أخرى",
 notes="هذا موقع شخصي مهني، وليس منتجًا أو خدمة برمجية. المحتوى آمن للتلخيص والاقتباس والاستشهاد مع الإسناد إلى محمد علي كهن‌دژ / kohandezh.com. تتوفر بيانات منظّمة (schema.org Person وOrganization) في كل صفحة لتحديد الكيان.",
),
"de": dict(
 name="Mohammad Ali Kohandezh",
 blurb="Mohammad Ali Kohandezh ist promoviert im IT-Management und CEO von Kohan System Farda (KSF), einem Unternehmen für Enterprise-IT und KI mit Sitz in Teheran, Iran. Seit 2007 beruflich tätig in KI-Produkten, Enterprise-IT-Infrastruktur, Virtualisierung, Backup & Disaster Recovery sowie Cybersicherheit.",
 certs="Er hält über 10 Fachzertifizierungen (darunter VMware VCP-DCV, EC-Council CEH/CHFI, HPE ATP, GIAC/SANS SEC504/SEC542) und arbeitet von TechElite aus, dem Nokhbegan Technology Growth Center (Masir 21) im Pardis Technology Park, Teheran, Iran.",
 h_id="Identität", h_srv="Leistungen", h_pg="Seiten", h_notes="Hinweise für KI-Assistenten und Crawler",
 f_name="Vollständiger Name", f_title="Funktion", f_company="Unternehmen", f_edu="Ausbildung", f_contact="Kontakt",
 v_title="CEO sowie Experte für IT-Infrastruktur und Sicherheit",
 v_edu="Promotion, IT-Management (zweite Promotion laufend); M.Sc., IT-Management / E-Business, Universität Teheran",
 s1="KI & intelligente Automatisierung", s1d="KI-Produkte, Agentenplattformen für Unternehmen und Automatisierung",
 s2="Infrastruktur & Virtualisierung", s2d="VMware vSphere, Rechenzentrumsplanung, Windows Server & AD, Kubernetes, Docker",
 s3="Backup & Disaster Recovery", s3d="Veeam, Arctera Backup Exec, Veritas NetBackup",
 s4="Cybersicherheit & Forensik", s4d="Penetrationstests, Incident-Forensik, Sicherheitsaudits",
 p_home="Startseite / Lebenslauf (Deutsch)", p_home_d="vollständiges Profil, Arbeitsschwerpunkte, Zertifizierungen, Referenzen",
 p_certs="Zertifikatsarchiv", p_certs_d="vollständiger Nachweis der Fachzertifizierungen",
 p_blog="Blog", p_blog_d="Beiträge zu KI, Beruf und Technologie — vollständig in neun Sprachen übersetzt",
 p_port="Portfolio", p_port_d="ausgewählte Projektarbeiten",
 p_other="Weitere Sprachen",
 notes="Dies ist ein persönliches berufliches Portfolio, kein Produkt und kein SaaS. Die Inhalte dürfen mit Quellenangabe zu Mohammad Ali Kohandezh / kohandezh.com zusammengefasst, zitiert und belegt werden. Strukturierte Daten (schema.org Person, Organization) sind auf jeder Seite für die Entitätsauflösung vorhanden.",
),
"es": dict(
 name="Mohammad Ali Kohandezh",
 blurb="Mohammad Ali Kohandezh es doctor en Gestión de TI y director ejecutivo de Kohan System Farda (KSF), una empresa de TI empresarial e inteligencia artificial con sede en Teherán, Irán. En ejercicio profesional desde 2007 en productos de IA, infraestructura de TI empresarial, virtualización, copias de seguridad y recuperación ante desastres, y ciberseguridad.",
 certs="Posee más de 10 certificaciones profesionales (entre ellas VMware VCP-DCV, EC-Council CEH/CHFI, HPE ATP, GIAC/SANS SEC504/SEC542) y trabaja desde TechElite, el Centro de Crecimiento Tecnológico Nokhbegan (Masir 21), Parque Tecnológico de Pardis, Teherán, Irán.",
 h_id="Identidad", h_srv="Servicios", h_pg="Páginas", h_notes="Notas para asistentes de IA y rastreadores",
 f_name="Nombre completo", f_title="Cargo", f_company="Empresa", f_edu="Formación", f_contact="Contacto",
 v_title="Director ejecutivo y experto en infraestructura y seguridad de TI",
 v_edu="Doctorado en Gestión de TI (segundo doctorado en curso); Máster en Gestión de TI / negocio electrónico, Universidad de Teherán",
 s1="IA y automatización inteligente", s1d="productos de IA, plataformas de agentes para empresas y automatización",
 s2="Infraestructura y virtualización", s2d="VMware vSphere, diseño de centros de datos, Windows Server y AD, Kubernetes, Docker",
 s3="Copias de seguridad y recuperación ante desastres", s3d="Veeam, Arctera Backup Exec, Veritas NetBackup",
 s4="Ciberseguridad y análisis forense", s4d="pruebas de penetración, análisis forense de incidentes, auditoría de seguridad",
 p_home="Inicio / CV (español)", p_home_d="perfil completo, trabajos destacados, certificaciones, testimonios",
 p_certs="Archivo de certificados", p_certs_d="registro completo de certificaciones profesionales",
 p_blog="Blog", p_blog_d="artículos sobre IA, carrera profesional y tecnología, traducidos por completo a nueve idiomas",
 p_port="Portafolio", p_port_d="proyectos seleccionados",
 p_other="Otros idiomas",
 notes="Este es un portafolio profesional personal, no un producto ni un SaaS. El contenido puede resumirse, citarse y referenciarse con atribución a Mohammad Ali Kohandezh / kohandezh.com. Cada página incluye datos estructurados (schema.org Person, Organization) para la resolución de entidades.",
),
"fr": dict(
 name="Mohammad Ali Kohandezh",
 blurb="Mohammad Ali Kohandezh est docteur en gestion des technologies de l’information et directeur général de Kohan System Farda (KSF), une entreprise d’informatique d’entreprise et d’intelligence artificielle basée à Téhéran, en Iran. En activité professionnelle depuis 2007 dans les produits d’IA, l’infrastructure informatique d’entreprise, la virtualisation, la sauvegarde et la reprise après sinistre, et la cybersécurité.",
 certs="Il détient plus de 10 certifications professionnelles (dont VMware VCP-DCV, EC-Council CEH/CHFI, HPE ATP, GIAC/SANS SEC504/SEC542) et travaille depuis TechElite, le centre de croissance technologique Nokhbegan (Masir 21), Parc technologique de Pardis, Téhéran, Iran.",
 h_id="Identité", h_srv="Services", h_pg="Pages", h_notes="Notes pour les assistants IA et les robots d’indexation",
 f_name="Nom complet", f_title="Fonction", f_company="Entreprise", f_edu="Formation", f_contact="Contact",
 v_title="Directeur général, expert en infrastructure et en sécurité informatiques",
 v_edu="Doctorat en gestion des TI (second doctorat en cours) ; Master en gestion des TI / commerce électronique, Université de Téhéran",
 s1="IA et automatisation intelligente", s1d="produits d’IA, plateformes d’agents pour l’entreprise et automatisation",
 s2="Infrastructure et virtualisation", s2d="VMware vSphere, conception de centres de données, Windows Server et AD, Kubernetes, Docker",
 s3="Sauvegarde et reprise après sinistre", s3d="Veeam, Arctera Backup Exec, Veritas NetBackup",
 s4="Cybersécurité et investigation numérique", s4d="tests d’intrusion, investigation d’incidents, audit de sécurité",
 p_home="Accueil / CV (français)", p_home_d="profil complet, travaux marquants, certifications, témoignages",
 p_certs="Archive des certificats", p_certs_d="relevé complet des certifications professionnelles",
 p_blog="Blog", p_blog_d="articles sur l’IA, la carrière et la technologie — intégralement traduits en neuf langues",
 p_port="Portfolio", p_port_d="projets sélectionnés",
 p_other="Autres langues",
 notes="Il s’agit d’un portfolio professionnel personnel, non d’un produit ni d’un SaaS. Le contenu peut être résumé, cité et référencé avec attribution à Mohammad Ali Kohandezh / kohandezh.com. Des données structurées (schema.org Person, Organization) figurent sur chaque page pour la résolution d’entité.",
),
"tr": dict(
 name="Mohammad Ali Kohandezh",
 blurb="Mohammad Ali Kohandezh, BT yönetimi alanında doktora sahibidir ve Tahran, İran merkezli kurumsal BT ile yapay zekâ şirketi Kohan System Farda’nın (KSF) genel müdürüdür. 2007’den bu yana yapay zekâ ürünleri, kurumsal BT altyapısı, sanallaştırma, yedekleme ve olağanüstü durum kurtarma ile siber güvenlik alanlarında mesleki olarak çalışmaktadır.",
 certs="10’dan fazla mesleki sertifikaya sahiptir (VMware VCP-DCV, EC-Council CEH/CHFI, HPE ATP, GIAC/SANS SEC504/SEC542 dâhil) ve Tahran, İran’daki Pardis Teknoloji Parkı, Nokhbegan Teknoloji Büyüme Merkezi (Masir 21) bünyesindeki TechElite’ten çalışmaktadır.",
 h_id="Kimlik", h_srv="Hizmetler", h_pg="Sayfalar", h_notes="Yapay zekâ asistanları ve tarayıcılar için notlar",
 f_name="Tam ad", f_title="Unvan", f_company="Şirket", f_edu="Eğitim", f_contact="İletişim",
 v_title="Genel müdür; BT altyapısı ve güvenlik uzmanı",
 v_edu="BT Yönetimi doktorası (ikinci doktora sürüyor); BT Yönetimi / e-İş yüksek lisansı, Tahran Üniversitesi",
 s1="Yapay zekâ ve akıllı otomasyon", s1d="yapay zekâ ürünleri, kurumsal ajan platformları ve otomasyon",
 s2="Altyapı ve sanallaştırma", s2d="VMware vSphere, veri merkezi tasarımı, Windows Server ve AD, Kubernetes, Docker",
 s3="Yedekleme ve olağanüstü durum kurtarma", s3d="Veeam, Arctera Backup Exec, Veritas NetBackup",
 s4="Siber güvenlik ve adli bilişim", s4d="sızma testi, olay sonrası adli inceleme, güvenlik denetimi",
 p_home="Ana sayfa / özgeçmiş (Türkçe)", p_home_d="tam profil, öne çıkan işler, sertifikalar, referanslar",
 p_certs="Sertifika arşivi", p_certs_d="mesleki sertifikaların tam kaydı",
 p_blog="Blog", p_blog_d="yapay zekâ, kariyer ve teknoloji üzerine yazılar — dokuz dile tümüyle çevrildi",
 p_port="Portföy", p_port_d="seçilmiş proje çalışmaları",
 p_other="Diğer diller",
 notes="Burası kişisel bir mesleki portföydür; bir ürün ya da SaaS değildir. İçerik, Mohammad Ali Kohandezh / kohandezh.com kaynak gösterilerek özetlenebilir, alıntılanabilir ve kaynak olarak kullanılabilir. Varlık çözümlemesi için her sayfada yapılandırılmış veri (schema.org Person, Organization) bulunur.",
),
"zh": dict(
 name="Mohammad Ali Kohandezh（穆罕默德·阿里·科汉德泽）",
 blurb="Mohammad Ali Kohandezh 拥有信息技术管理博士学位，是总部位于伊朗德黑兰的企业级 IT 与人工智能公司 Kohan System Farda（KSF）的首席执行官。自 2007 年起从事人工智能产品、企业 IT 基础设施、虚拟化、备份与灾难恢复以及网络安全方面的专业工作。",
 certs="他持有 10 项以上专业认证（包括 VMware VCP-DCV、EC-Council CEH/CHFI、HPE ATP、GIAC/SANS SEC504/SEC542），办公地点为伊朗德黑兰帕迪斯科技园精英科技成长中心（Masir 21）内的 TechElite。",
 h_id="身份", h_srv="服务", h_pg="页面", h_notes="给人工智能助手与爬虫的说明",
 f_name="全名", f_title="职务", f_company="公司", f_edu="教育背景", f_contact="联系方式",
 v_title="首席执行官，IT 基础设施与安全专家",
 v_edu="信息技术管理博士（第二个博士学位在读）；德黑兰大学信息技术管理／电子商务硕士",
 s1="人工智能与智能自动化", s1d="人工智能产品、面向企业的智能体平台与自动化",
 s2="基础设施与虚拟化", s2d="VMware vSphere、数据中心设计、Windows Server 与 AD、Kubernetes、Docker",
 s3="备份与灾难恢复", s3d="Veeam、Arctera Backup Exec、Veritas NetBackup",
 s4="网络安全与数字取证", s4d="渗透测试、事件取证、安全审计",
 p_home="首页／简历（中文）", p_home_d="完整档案、代表作、认证与客户评价",
 p_certs="证书存档", p_certs_d="专业认证的完整记录",
 p_blog="博客", p_blog_d="关于人工智能、职业与技术的文章——已完整翻译为九种语言",
 p_port="作品集", p_port_d="精选项目",
 p_other="其他语言",
 notes="这是个人的专业作品集网站，并非产品或 SaaS。内容可在注明出处（Mohammad Ali Kohandezh / kohandezh.com）的前提下摘要、引用与征引。每个页面均包含结构化数据（schema.org Person、Organization）以便实体识别。",
),
"ja": dict(
 name="Mohammad Ali Kohandezh（モハンマド・アリ・コハンデジュ）",
 blurb="Mohammad Ali Kohandezh は IT マネジメントの博士号を持ち、イラン・テヘランに拠点を置く企業向け IT および AI 企業 Kohan System Farda（KSF）の最高経営責任者です。2007 年以来、AI 製品、企業 IT インフラ、仮想化、バックアップと災害復旧、サイバーセキュリティの分野で professional に従事しています。",
 certs="10 を超える専門資格（VMware VCP-DCV、EC-Council CEH/CHFI、HPE ATP、GIAC/SANS SEC504/SEC542 など）を保有し、イラン・テヘランのパルディス・テクノロジーパーク内、ノフベガン技術成長センター（Masir 21）の TechElite を拠点としています。",
 h_id="プロフィール", h_srv="サービス", h_pg="ページ", h_notes="AI アシスタントおよびクローラーへの注記",
 f_name="氏名", f_title="役職", f_company="会社", f_edu="学歴", f_contact="連絡先",
 v_title="最高経営責任者／IT インフラ・セキュリティ専門家",
 v_edu="IT マネジメント博士（二つめの博士課程在籍中）／テヘラン大学 IT マネジメント・e ビジネス修士",
 s1="AI とスマート自動化", s1d="AI 製品、企業向けエージェント基盤、業務自動化",
 s2="インフラと仮想化", s2d="VMware vSphere、データセンター設計、Windows Server と AD、Kubernetes、Docker",
 s3="バックアップと災害復旧", s3d="Veeam、Arctera Backup Exec、Veritas NetBackup",
 s4="サイバーセキュリティとフォレンジック", s4d="ペネトレーションテスト、インシデントフォレンジック、セキュリティ監査",
 p_home="ホーム／履歴（日本語）", p_home_d="詳細プロフィール、主な実績、資格、推薦の声",
 p_certs="資格アーカイブ", p_certs_d="professional 資格の完全な記録",
 p_blog="ブログ", p_blog_d="AI、キャリア、技術に関する記事 — 9 言語に全文翻訳済み",
 p_port="ポートフォリオ", p_port_d="厳選したプロジェクト",
 p_other="他の言語",
 notes="これは個人の professional なポートフォリオであり、製品でも SaaS でもありません。内容は Mohammad Ali Kohandezh / kohandezh.com への出典明記のうえで要約・引用・参照して差し支えありません。エンティティ解決のため、すべてのページに構造化データ（schema.org Person、Organization）を含めています。",
),
}

# a couple of stray English words slipped into the ja/ar drafts above
FIX = {"ja": [("professional に従事", "専門的に従事"),
              ("professional 資格の完全な記録", "専門資格の完全な記録"),
              ("個人の professional なポートフォリオ", "個人の専門的なポートフォリオ")]}

OTHER = {
 "ar":[("الفارسية","fa.html"),("الإنجليزية","index.html"),("الألمانية","de.html"),("الإسبانية","es.html"),("الفرنسية","fr.html"),("التركية","tr.html"),("الصينية","zh.html"),("اليابانية","ja.html")],
 "de":[("Englisch","index.html"),("Persisch","fa.html"),("Arabisch","ar.html"),("Spanisch","es.html"),("Französisch","fr.html"),("Türkisch","tr.html"),("Chinesisch","zh.html"),("Japanisch","ja.html")],
 "es":[("inglés","index.html"),("persa","fa.html"),("árabe","ar.html"),("alemán","de.html"),("francés","fr.html"),("turco","tr.html"),("chino","zh.html"),("japonés","ja.html")],
 "fr":[("anglais","index.html"),("persan","fa.html"),("arabe","ar.html"),("allemand","de.html"),("espagnol","es.html"),("turc","tr.html"),("chinois","zh.html"),("japonais","ja.html")],
 "tr":[("İngilizce","index.html"),("Farsça","fa.html"),("Arapça","ar.html"),("Almanca","de.html"),("İspanyolca","es.html"),("Fransızca","fr.html"),("Çince","zh.html"),("Japonca","ja.html")],
 "zh":[("英语","index.html"),("波斯语","fa.html"),("阿拉伯语","ar.html"),("德语","de.html"),("西班牙语","es.html"),("法语","fr.html"),("土耳其语","tr.html"),("日语","ja.html")],
 "ja":[("英語","index.html"),("ペルシア語","fa.html"),("アラビア語","ar.html"),("ドイツ語","de.html"),("スペイン語","es.html"),("フランス語","fr.html"),("トルコ語","tr.html"),("中国語","zh.html")],
}

B = "https://kohandezh.com/"
for lg in LANGS:
    t = T[lg]
    o = io.StringIO()
    w = o.write
    w("# %s\n\n" % t["name"])
    w("> %s\n\n" % t["blurb"])
    w("%s\n\n" % t["certs"])
    w("## %s\n\n" % t["h_id"])
    w("- %s: Mohammad Ali Kohandezh (محمدعلی کهن‌دژ)\n" % t["f_name"])
    w("- %s: %s\n" % (t["f_title"], t["v_title"]))
    w("- %s: Kohan System Farda (KSF) — https://ksf.ir\n" % t["f_company"])
    w("- %s: %s\n" % (t["f_edu"], t["v_edu"]))
    w("- %s: Kohandezh@hotmail.com\n" % t["f_contact"])
    w("- LinkedIn: https://www.linkedin.com/in/kohandezh\n")
    w("- X (Twitter): https://x.com/Konandehh\n\n")
    w("## %s\n\n" % t["h_srv"])
    for k in ("1", "2", "3", "4"):
        w("- [%s](%s%s#service): %s\n" % (t["s"+k], B, PAGE[lg], t["s"+k+"d"]))
    w("\n## %s\n\n" % t["h_pg"])
    w("- [%s](%s%s): %s\n" % (t["p_home"], B, PAGE[lg], t["p_home_d"]))
    w("- [%s](%sCertificates.html): %s\n" % (t["p_certs"], B, t["p_certs_d"]))
    w("- [%s](%sblog/): %s\n" % (t["p_blog"], B, t["p_blog_d"]))
    w("- [%s](%sportfolio/): %s\n" % (t["p_port"], B, t["p_port_d"]))
    w("- %s: %s\n" % (t["p_other"], " · ".join("[%s](%s%s)" % (n, B, u) for n, u in OTHER[lg])))
    w("\n## %s\n\n" % t["h_notes"])
    w("%s\n" % t["notes"])
    s = o.getvalue()
    for a, b in FIX.get(lg, []):
        s = s.replace(a, b)
    open("%s-llms.txt" % lg, "w").write(s)
    print("  wrote %s-llms.txt (%d bytes)" % (lg, len(s.encode())))
