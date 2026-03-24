<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require '../../PHPMailer/src/PHPMailer.php';
require '../../PHPMailer/src/Exception.php';
require '../../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Config SMTP
$SMTP_HOST = 'smtp.gmail.com';
$SMTP_USER = 'ma.proyectos@grupo-cise.com';
$SMTP_PASS = 'xyqp tfbj irvu ulcn';
$SMTP_PORT = 587;
$SMTP_SECURE = 'tls';

// Defaults
$default_batch_size = 30;
$default_sleep_seconds = 2;

// Helpers
function parse_emails($text){
    $text = str_replace(["\r",";"], ["",","], $text);
    $parts = preg_split('/[\n,]+/', $text);
    $emails = [];
    foreach($parts as $p){
        $p = trim($p);
        if(!$p) continue;
        if(preg_match('/(.*)<([^>]+)>/', $p, $m)){
            $emails[] = ['email'=>trim($m[2]), 'name'=>trim($m[1])];
        }else{
            $emails[] = ['email'=>$p,'name'=>''];
        }
    }
    return $emails;
}

function parse_csv($tmp){
    $rows=[];
    if(($handle=fopen($tmp,"r"))!==FALSE){
        while(($data=fgetcsv($handle,1000,","))!==FALSE){
            if(count($data)>=1){
                $email=trim($data[0]);
                $name=isset($data[1])?trim($data[1]):'';
                if($email)$rows[]= ['email'=>$email,'name'=>$name];
            }
        }
        fclose($handle);
    }
    return $rows;
}

$report=['total'=>0,'sent'=>0,'failed'=>0,'details'=>[]];

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='send'){
    $from_email = $_POST['from_email']??$SMTP_USER;
    $from_name  = $_POST['from_name']??'Remitente';
    $subject    = $_POST['subject']??'(sin asunto)';
    $body_raw   = $_POST['body']??'';
    $recipients_text = $_POST['recipients']??'';
    $batch_size = max(1,(int)($_POST['batch_size']??$default_batch_size));
    $sleep_seconds = max(0,(int)($_POST['sleep_seconds']??$default_sleep_seconds));
    $max_retries = max(0,(int)($_POST['max_retries']??1));

    $recipients = parse_emails($recipients_text);

    if(!empty($_FILES['csv_file']['tmp_name']) && $_FILES['csv_file']['error']===UPLOAD_ERR_OK){
        $csv_recip = parse_csv($_FILES['csv_file']['tmp_name']);
        $recipients = array_merge($recipients, $csv_recip);
    }

    $final_recipients=[];
    $seen=[];
    foreach($recipients as $r){
        $email = filter_var($r['email'], FILTER_SANITIZE_EMAIL);
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) continue;
        if(isset($seen[strtolower($email)])) continue;
        $seen[strtolower($email)]=true;
        $final_recipients[]=$r;
    }

    $report['total']=count($final_recipients);
    $counter=0;

    foreach($final_recipients as $r){
        $counter++;
        $attempt=0; $sent_ok=false; $last_error='';
        while($attempt<=$max_retries && !$sent_ok){
            $attempt++;
            $mail = new PHPMailer(true);
            try{
                $mail->isSMTP();
                $mail->Host=$SMTP_HOST;
                $mail->SMTPAuth=true;
                $mail->Username=$SMTP_USER;
                $mail->Password=$SMTP_PASS;
                $mail->SMTPSecure=$SMTP_SECURE==='ssl'?PHPMailer::ENCRYPTION_SMTPS:PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port=$SMTP_PORT;
                $mail->setFrom($from_email,$from_name);
                $mail->addAddress($r['email'],$r['name']?:null);
                $mail->isHTML(true);
                $mail->Subject=$subject;
                $body=str_replace(['{{email}}','{{name}}'],[$r['email'],$r['name']],$body_raw);
                $mail->Body=$body;
                $mail->AltBody=strip_tags($body);
                $mail->send();
                $sent_ok=true;
                $report['sent']++;
                $report['details'][]=['email'=>$r['email'],'name'=>$r['name'],'status'=>'enviado','attempts'=>$attempt,'error'=>''];
            }catch(Exception $e){
                $last_error = $mail->ErrorInfo?:$e->getMessage();
                if($attempt<=$max_retries) sleep(1);
            }
        }
        if(!$sent_ok){
            $report['failed']++;
            $report['details'][]=['email'=>$r['email'],'name'=>$r['name'],'status'=>'error','attempts'=>$attempt,'error'=>$last_error];
        }
        if($batch_size>0 && $counter%$batch_size===0) sleep($sleep_seconds);
    }
}
?>

<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Mass Mailer Pro - Sistema Profesional de Email Marketing</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<style>
  :root {
    --primary: #4361ee;
    --secondary: #3f37c9;
    --success: #4cc9f0;
    --danger: #f72585;
    --warning: #f8961e;
    --info: #4895ef;
    --light: #f8f9fa;
    --dark: #212529;
    --gray: #6c757d;
    --light-gray: #e9ecef;
  }

  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }

  body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    padding: 20px;
    line-height: 1.6;
    color: var(--dark);
    min-height: 100vh;
  }

  .container {
    max-width: 1200px;
    margin: 0 auto;
    background: #fff;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 10px 30px rgba(0,0,0,.1);
  }

  .header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--light-gray);
  }

  .header h1 {
    color: var(--primary);
    font-size: 2.2rem;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
  }

  .header p {
    color: var(--gray);
    font-size: 1.1rem;
  }

  .tabs {
    display: flex;
    margin-bottom: 25px;
    border-bottom: 1px solid var(--light-gray);
  }

  .tab {
    padding: 12px 24px;
    cursor: pointer;
    font-weight: 600;
    color: var(--gray);
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
  }

  .tab.active {
    color: var(--primary);
    border-bottom: 3px solid var(--primary);
  }

  .tab:hover:not(.active) {
    color: var(--secondary);
  }

  .tab-content {
    display: none;
  }

  .tab-content.active {
    display: block;
    animation: fadeIn 0.5s ease;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }

  .form-group {
    margin-bottom: 20px;
  }

  label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--dark);
  }

  .form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--light-gray);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.3s ease;
  }

  .form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
  }

  .form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
  }

  .form-col {
    flex: 1;
  }

  .btn {
    background: var(--primary);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .btn:hover {
    background: var(--secondary);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
  }

  .btn-block {
    display: block;
    width: 100%;
    text-align: center;
    justify-content: center;
  }

  .btn-success {
    background: var(--success);
  }

  .btn-success:hover {
    background: #3aa8d5;
  }

  .btn-danger {
    background: var(--danger);
  }

  .btn-danger:hover {
    background: #e11570;
  }

  .small {
    font-size: 0.875rem;
    color: var(--gray);
    margin-bottom: 10px;
  }

  .card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid var(--light-gray);
  }

  .card-header {
    padding-bottom: 15px;
    margin-bottom: 15px;
    border-bottom: 1px solid var(--light-gray);
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .card-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
  }

  .templates-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 15px;
  }

  .template-card {
    border: 2px solid var(--light-gray);
    border-radius: 10px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
  }

  .template-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    border-color: var(--primary);
  }

  .template-card.active {
    border-color: var(--primary);
    box-shadow: 0 5px 15px rgba(67, 97, 238, 0.2);
  }

  .template-preview {
    height: 150px;
    background-size: cover;
    background-position: center;
    position: relative;
  }

  .template-info {
    padding: 15px;
  }

  .template-name {
    font-weight: 600;
    margin-bottom: 5px;
  }

  .template-desc {
    font-size: 0.875rem;
    color: var(--gray);
  }

  .preview-container {
    border: 1px solid var(--light-gray);
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
    background: white;
    max-height: 400px;
    overflow-y: auto;
  }

  .preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--light-gray);
  }

  .preview-title {
    font-weight: 600;
    color: var(--dark);
  }

  .stats {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
  }

  .stat-card {
    flex: 1;
    text-align: center;
    padding: 15px;
    border-radius: 10px;
    background: white;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
  }

  .stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
  }

  .stat-label {
    font-size: 0.875rem;
    color: var(--gray);
  }

  .stat-success .stat-value {
    color: #28a745;
  }

  .stat-danger .stat-value {
    color: var(--danger);
  }

  .stat-info .stat-value {
    color: var(--info);
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
    font-size: 0.9rem;
  }

  th, td {
    padding: 12px 15px;
    border: 1px solid var(--light-gray);
    text-align: left;
  }

  th {
    background-color: var(--light);
    font-weight: 600;
  }

  .success {
    color: #28a745;
    font-weight: 600;
  }

  .error {
    color: var(--danger);
    font-weight: 600;
  }

  .warning {
    color: var(--warning);
    font-weight: 600;
  }

  .badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
  }

  .badge-success {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
  }

  .badge-danger {
    background: rgba(247, 37, 133, 0.1);
    color: var(--danger);
  }

  .alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
  }

  .alert-success {
    background: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.2);
    color: #155724;
  }

  .alert-danger {
    background: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.2);
    color: #721c24;
  }

  @media (max-width: 768px) {
    .form-row {
      flex-direction: column;
      gap: 0;
    }
    
    .templates-grid {
      grid-template-columns: 1fr;
    }
    
    .stats {
      flex-direction: column;
    }
    
    .tab {
      padding: 10px 15px;
      font-size: 0.9rem;
    }
  }
</style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1><i class="fas fa-paper-plane"></i> Mass Mailer Pro</h1>
    <p>Sistema profesional de envío masivo de correos electrónicos</p>
  </div>

  <div class="tabs">
    <div class="tab active" data-tab="config">Configuración</div>
    <div class="tab" data-tab="templates">Plantillas</div>
    <div class="tab" data-tab="recipients">Destinatarios</div>
    <div class="tab" data-tab="preview">Vista Previa</div>
    <div class="tab" data-tab="send">Enviar</div>
  </div>

  <form method="post" enctype="multipart/form-data" id="emailForm">
    <input type="hidden" name="action" value="send">

    <div class="tab-content active" id="config-tab">
      <div class="card">
        <div class="card-header">
          <i class="fas fa-cog"></i>
          <h3 class="card-title">Configuración del Remitente</h3>
        </div>
        
        <div class="form-row">
          <div class="form-col">
            <label for="from_email">Email del Remitente</label>
            <input type="email" class="form-control" id="from_email" name="from_email" value="<?php echo htmlspecialchars($SMTP_USER); ?>" required>
          </div>
          <div class="form-col">
            <label for="from_name">Nombre del Remitente</label>
            <input type="text" class="form-control" id="from_name" name="from_name" value="DevKabzo" required>
          </div>
        </div>
        
        <div class="form-group">
          <label for="subject">Asunto del Correo</label>
          <input type="text" class="form-control" id="subject" name="subject" value="Comunicación importante" required>
        </div>
      </div>
      
      <div class="card">
        <div class="card-header">
          <i class="fas fa-sliders-h"></i>
          <h3 class="card-title">Configuración de Envío</h3>
        </div>
        
        <div class="form-row">
          <div class="form-col">
            <label for="batch_size">Tamaño del Lote</label>
            <input type="number" class="form-control" id="batch_size" name="batch_size" value="<?php echo $default_batch_size; ?>" min="1">
            <small class="small">Número de correos a enviar antes de pausar</small>
          </div>
          <div class="form-col">
            <label for="sleep_seconds">Pausa (segundos)</label>
            <input type="number" class="form-control" id="sleep_seconds" name="sleep_seconds" value="<?php echo $default_sleep_seconds; ?>" min="0" step="1">
            <small class="small">Tiempo de pausa entre lotes</small>
          </div>
          <div class="form-col">
            <label for="max_retries">Máx. Reintentos</label>
            <input type="number" class="form-control" id="max_retries" name="max_retries" value="1" min="0">
            <small class="small">Intentos por destinatario en caso de error</small>
          </div>
        </div>
      </div>
      
      <div class="form-group" style="text-align: right;">
        <button type="button" class="btn next-tab" data-next="templates">
          Siguiente <i class="fas fa-arrow-right"></i>
        </button>
      </div>
    </div>

    <div class="tab-content" id="templates-tab">
      <div class="card">
        <div class="card-header">
          <i class="fas fa-palette"></i>
          <h3 class="card-title">Selecciona una Plantilla</h3>
        </div>
        
        <div class="templates-grid">
          <div class="template-card" data-template="business">
            <div class="template-preview" style="background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);"></div>
            <div class="template-info">
              <div class="template-name">Corporativo</div>
              <div class="template-desc">Profesional para empresas</div>
            </div>
          </div>
          
          <div class="template-card" data-template="newsletter">
            <div class="template-preview" style="background: linear-gradient(135deg, #f72585 0%, #b5179e 100%);"></div>
            <div class="template-info">
              <div class="template-name">Newsletter</div>
              <div class="template-desc">Para boletines informativos</div>
            </div>
          </div>
          
          <div class="template-card" data-template="promotion">
            <div class="template-preview" style="background: linear-gradient(135deg, #f8961e 0%, #f3722c 100%);"></div>
            <div class="template-info">
              <div class="template-name">Promocional</div>
              <div class="template-desc">Ofertas y descuentos</div>
            </div>
          </div>
          
          <div class="template-card" data-template="event">
            <div class="template-preview" style="background: linear-gradient(135deg, #4cc9f0 0%, #4895ef 100%);"></div>
            <div class="template-info">
              <div class="template-name">Aviso KABZO</div>
              <div class="template-desc">Invitaciones a eventos</div>
            </div>
          </div>
          
          <div class="template-card" data-template="holiday">
            <div class="template-preview" style="background: linear-gradient(135deg, #06d6a0 0%, #1b9aaa 100%);"></div>
            <div class="template-info">
              <div class="template-name">Festivo</div>
              <div class="template-desc">Saludos de temporada</div>
            </div>
          </div>
          
          <div class="template-card" data-template="minimal">
            <div class="template-preview" style="background: linear-gradient(135deg, #6a4c93 0%, #1982c4 100%);"></div>
            <div class="template-info">
              <div class="template-name">Minimalista</div>
              <div class="template-desc">Diseño limpio y simple</div>
            </div>
          </div>
          
          <div class="template-card" data-template="elegant">
            <div class="template-preview" style="background: linear-gradient(135deg, #2b2d42 0%, #8d99ae 100%);"></div>
            <div class="template-info">
              <div class="template-name">Elegante</div>
              <div class="template-desc">Para ocasiones especiales</div>
            </div>
          </div>
          
          <div class="template-card" data-template="custom">
            <div class="template-preview" style="background: linear-gradient(135deg, #ff9e00 0%, #ff6b6b 100%);"></div>
            <div class="template-info">
              <div class="template-name">Personalizado</div>
              <div class="template-desc">Comienza desde cero</div>
            </div>
          </div>
        </div>
      </div>
      
      <div class="card">
        <div class="card-header">
          <i class="fas fa-edit"></i>
          <h3 class="card-title">Editor de Contenido</h3>
        </div>
        
        <div class="form-group">
          <label for="body">Cuerpo del Correo</label>
          <textarea id="body" name="body" class="form-control" style="height: 300px;"></textarea>
          <small class="small">Usa <code>{{name}}</code> y <code>{{email}}</code> para personalizar cada correo</small>
        </div>
      </div>
      
      <div class="form-group" style="display: flex; justify-content: space-between;">
        <button type="button" class="btn prev-tab" data-prev="config">
          <i class="fas fa-arrow-left"></i> Anterior
        </button>
        <button type="button" class="btn next-tab" data-next="recipients">
          Siguiente <i class="fas fa-arrow-right"></i>
        </button>
      </div>
    </div>

    <div class="tab-content" id="recipients-tab">
      <div class="card">
        <div class="card-header">
          <i class="fas fa-users"></i>
          <h3 class="card-title">Lista de Destinatarios</h3>
        </div>
        
        <div class="form-group">
          <label for="recipients">Destinatarios (uno por línea o separados por comas)</label>
          <textarea id="recipients" name="recipients" class="form-control" placeholder="ejemplo@dominio.com, Juan Pérez <juan@ejemplo.com>"></textarea>
          <small class="small">Formato: email o "Nombre" &lt;email&gt;</small>
        </div>
        
        <div class="form-group">
          <label for="csv_file">O sube un archivo CSV</label>
          <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv">
          <small class="small">El CSV debe tener al menos una columna con emails, y opcionalmente nombres en la segunda columna</small>
        </div>
        
        <div class="form-group">
          <button type="button" class="btn" id="validateEmails">
            <i class="fas fa-check-circle"></i> Validar Emails
          </button>
          <span id="emailCount" class="badge" style="margin-left: 10px; display: none;">0 emails válidos</span>
        </div>
      </div>
      
      <div class="form-group" style="display: flex; justify-content: space-between;">
        <button type="button" class="btn prev-tab" data-prev="templates">
          <i class="fas fa-arrow-left"></i> Anterior
        </button>
        <button type="button" class="btn next-tab" data-next="preview">
          Siguiente <i class="fas fa-arrow-right"></i>
        </button>
      </div>
    </div>

    <div class="tab-content" id="preview-tab">
      <div class="card">
        <div class="card-header">
          <i class="fas fa-eye"></i>
          <h3 class="card-title">Vista Previa del Correo</h3>
        </div>
        
        <div class="preview-header">
          <div class="preview-title">Asunto: <span id="preview-subject">Comunicación importante</span></div>
          <button type="button" class="btn" id="refreshPreview">
            <i class="fas fa-sync-alt"></i> Actualizar Vista Previa
          </button>
        </div>
        
        <div class="preview-container" id="emailPreview">
          <!-- Vista previa del email se cargará aquí -->
        </div>
      </div>
      
      <div class="form-group" style="display: flex; justify-content: space-between;">
        <button type="button" class="btn prev-tab" data-prev="recipients">
          <i class="fas fa-arrow-left"></i> Anterior
        </button>
        <button type="button" class="btn next-tab" data-next="send">
          Siguiente <i class="fas fa-arrow-right"></i>
        </button>
      </div>
    </div>

    <div class="tab-content" id="send-tab">
      <div class="card">
        <div class="card-header">
          <i class="fas fa-paper-plane"></i>
          <h3 class="card-title">Enviar Correos</h3>
        </div>
        
        <div class="alert alert-info">
          <i class="fas fa-info-circle"></i> 
          <strong>Resumen:</strong> 
          <span id="summary-text">Se enviarán X correos con la plantilla Y</span>
        </div>
        
        <div class="form-group">
          <button type="submit" class="btn btn-success btn-block">
            <i class="fas fa-rocket"></i> Iniciar Envío Masivo
          </button>
          <small class="small">Revisa toda la configuración antes de enviar</small>
        </div>
      </div>
      
      <div class="form-group">
        <button type="button" class="btn prev-tab" data-prev="preview">
          <i class="fas fa-arrow-left"></i> Anterior
        </button>
      </div>
    </div>
  </form>

  <?php if(!empty($report) && $_SERVER['REQUEST_METHOD']==='POST'): ?>
  <div class="card" style="margin-top: 30px;">
    <div class="card-header">
      <i class="fas fa-chart-bar"></i>
      <h3 class="card-title">Reporte de Envío</h3>
    </div>
    
    <div class="stats">
      <div class="stat-card stat-info">
        <div class="stat-value"><?php echo $report['total']; ?></div>
        <div class="stat-label">Total</div>
      </div>
      <div class="stat-card stat-success">
        <div class="stat-value"><?php echo $report['sent']; ?></div>
        <div class="stat-label">Enviados</div>
      </div>
      <div class="stat-card stat-danger">
        <div class="stat-value"><?php echo $report['failed']; ?></div>
        <div class="stat-label">Fallidos</div>
      </div>
    </div>
    
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Email</th>
          <th>Nombre</th>
          <th>Estado</th>
          <th>Intentos</th>
          <th>Error</th>
        </tr>
      </thead>
      <tbody>
        <?php $i=0; foreach($report['details'] as $d): $i++; ?>
        <tr>
          <td><?php echo $i;?></td>
          <td><?php echo htmlspecialchars($d['email']);?></td>
          <td><?php echo htmlspecialchars($d['name']);?></td>
          <td>
            <?php if($d['status']==='enviado'): ?>
              <span class="badge badge-success">enviado</span>
            <?php else: ?>
              <span class="badge badge-danger">error</span>
            <?php endif; ?>
          </td>
          <td><?php echo $d['attempts'];?></td>
          <td><?php echo htmlspecialchars($d['error']);?></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<script>
$(document).ready(function(){
    // Inicializar Summernote
    $('#body').summernote({
        placeholder: 'Escribe tu correo aquí...',
        tabsize: 2,
        height: 300,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'underline', 'clear']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['table', ['table']],
            ['insert', ['link', 'picture']],
            ['view', ['fullscreen', 'codeview', 'help']]
        ]
    });

    // Plantillas para diferentes ocasiones
    const templates = {
        'business': `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;">
            <div style="background: #4361ee; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0;">KABZO</h1>
                <p style="margin: 5px 0 0; opacity: 0.9;">Soluciones profesionales</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px;">
                <h2 style="color: #333; margin-top: 0;">Hola {{name}},</h2>
                <p style="color: #555; line-height: 1.6;">Nos complace contactarte para informarte sobre nuestras soluciones profesionales diseñadas para impulsar tu negocio.</p>
                <p style="color: #555; line-height: 1.6;">Hemos notado que tu dirección de correo es <strong>{{email}}</strong>, y creemos que nuestros servicios pueden ser de gran valor para ti.</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="#" style="background: #4361ee; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;">Conocer más</a>
                </div>
                <p style="color: #555; line-height: 1.6;">Si tienes alguna pregunta, no dudes en contactarnos.</p>
                <p style="color: #555; line-height: 1.6;">Atentamente,<br>El equipo de KABZO</p>
            </div>
            <div style="text-align: center; padding: 20px; color: #777; font-size: 14px;">
                <p>© 2023 KABZO. Todos los derechos reservados.</p>
            </div>
        </div>`,
        
        'newsletter': `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;">
            <div style="background: #f72585; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0;">NEWSLETTER</h1>
                <p style="margin: 5px 0 0; opacity: 0.9;">Mantente informado</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px;">
                <h2 style="color: #333; margin-top: 0;">Hola {{name}},</h2>
                <p style="color: #555; line-height: 1.6;">¡Bienvenido a nuestro boletín informativo! Aquí tienes las últimas novedades:</p>
                
                <div style="border-left: 4px solid #f72585; padding-left: 15px; margin: 20px 0;">
                    <h3 style="color: #f72585; margin: 0;">Nuevo lanzamiento</h3>
                    <p style="color: #555; margin: 5px 0;">Hemos lanzado nuevas funciones que te encantarán.</p>
                </div>
                
                <div style="border-left: 4px solid #4361ee; padding-left: 15px; margin: 20px 0;">
                    <h3 style="color: #4361ee; margin: 0;">Próximos eventos</h3>
                    <p style="color: #555; margin: 5px 0;">No te pierdas nuestro webinar gratuito la próxima semana.</p>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="#" style="background: #f72585; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;">Leer más</a>
                </div>
                
                <p style="color: #555; line-height: 1.6;">Gracias por ser parte de nuestra comunidad.</p>
            </div>
            <div style="text-align: center; padding: 20px; color: #777; font-size: 14px;">
                <p>¿No deseas recibir más estos correos? <a href="#" style="color: #f72585;">Darse de baja</a></p>
            </div>
        </div>`,
        
        'promotion': `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;">
            <div style="background: #f8961e; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; position: relative;">
                <span style="position: absolute; top: 10px; right: 10px; background: #f3722c; padding: 5px 10px; border-radius: 20px; font-size: 12px;">OFERTA</span>
                <h1 style="margin: 0;">¡Oferta Especial!</h1>
                <p style="margin: 5px 0 0; opacity: 0.9;">Solo por tiempo limitado</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px;">
                <h2 style="color: #333; margin-top: 0;">Hola {{name}},</h2>
                <p style="color: #555; line-height: 1.6;">Tenemos una oferta especial solo para ti. Aprovecha nuestro descuento exclusivo del <strong>30%</strong> en todos nuestros productos.</p>
                
                <div style="background: #fff9e6; border: 1px dashed #f8961e; padding: 15px; text-align: center; margin: 20px 0; border-radius: 5px;">
                    <h3 style="color: #f8961e; margin: 0;">Código de descuento:</h3>
                    <p style="font-size: 24px; font-weight: bold; color: #f3722c; margin: 10px 0;">PROMO30</p>
                </div>
                
                <p style="color: #555; line-height: 1.6;">Esta oferta es válida hasta el 30 de noviembre. ¡No te la pierdas!</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="#" style="background: #f8961e; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;">Aprovechar Oferta</a>
                </div>
                
                <p style="color: #555; line-height: 1.6;">Si tienes alguna pregunta, contáctanos respondiendo a este correo.</p>
            </div>
            <div style="text-align: center; padding: 20px; color: #777; font-size: 14px;">
                <p>© 2023 KABZO. Todos los derechos reservados.</p>
            </div>
        </div>`,
        
        'event': `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;">
    <div style="background: linear-gradient(135deg, #D4AF37 0%, #B8860B 100%); color: white; padding: 25px; text-align: center; border-radius: 10px 10px 0 0;">
        <h1 style="margin: 0; font-size: 24px;">VERIFICACIÓN DE INFORMACIÓN</h1>
        <p style="margin: 5px 0 0; opacity: 0.9;">GRUPO KABZO - Área de Desarrollo</p>
    </div>
    
    <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h2 style="color: #333; margin-top: 0;">Estimado/a {{name}},</h2>
        
        <p style="color: #555; line-height: 1.6; font-size: 16px;">
            Por medio del presente, solicitamos amablemente verificar la información de su equipo de cómputo, 
            incluyendo sus datos personales y configuración del sistema.
        </p>
        
        <div style="background: #fff9e6; border-left: 4px solid #D4AF37; padding: 15px; margin: 20px 0;">
            <h3 style="color: #B8860B; margin: 0 0 10px 0; font-size: 18px;">Instrucciones importantes:</h3>
            <p style="margin: 8px 0; color: #555;">
                • <strong>REGISTRO OBLIGATORIO:</strong> Debe registrarse en la plataforma con el correo: <strong>{{email}}</strong><br>
                • Verifique cuidadosamente toda la información personal en su PC<br>
                • Consulte el video tutorial para el proceso paso a paso<br>
                • Complete la verificación antes de la fecha establecida
            </p>
        </div>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="#" style="background: #D4AF37; color: black; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin: 0 10px; border: 2px solid #B8860B;">
                Ver Video Tutorial
            </a>
            <a href="https://internos.micontabilidad360.com/index.php" style="background: #000000; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold; margin: 0 10px; border: 2px solid #333;">
                Acceder a Plataforma
            </a>
        </div>
        
        <p style="color: #555; line-height: 1.6; font-size: 16px;">
            Este proceso es <strong>OBLIGATORIO</strong> para todos los colaboradores de <strong>GRUPO KABZO</strong> y forma parte de nuestras 
            actualizaciones automáticas desarrolladas por el Área de Desarrollo.
        </p>
        
        <p style="color: #555; line-height: 1.6; font-size: 16px;">
            Agradecemos su atención y pronta respuesta a esta solicitud.
        </p>
        
        <div style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #eee;">
            <p style="color: #777; margin: 5px 0;">Atentamente,</p>
            <p style="color: #D4AF37; font-weight: bold; margin: 5px 0;">Área de Desarrollo - GRUPO KABZO</p>
        </div>
    </div>
    
    <div style="text-align: center; padding: 20px; color: #777; font-size: 14px; background: #000; color: #D4AF37; border-radius: 0 0 10px 10px;">
        <p style="margin: 0;">© 2025 GRUPO KABZO. Todos los derechos reservados.</p>
        <p style="margin: 5px 0 0; font-size: 12px; opacity: 0.8;">Mensaje automático generado por el sistema</p>
    </div>
</div>`,
        
        'holiday': `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;">
            <div style="background: #06d6a0; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0;">¡Felices Fiestas!</h1>
                <p style="margin: 5px 0 0; opacity: 0.9;">Que esta temporada esté llena de alegría</p>
            </div>
            <div style="background: white; padding: 30px; border-radius: 0 0 10px 10px; text-align: center;">
                <h2 style="color: #333; margin-top: 0;">Hola {{name}},</h2>
                <p style="color: #555; line-height: 1.6;">En esta temporada festiva, queremos desearte lo mejor para ti y tus seres queridos.</p>
                
                <div style="margin: 25px 0;">
                    <p style="font-size: 18px; font-style: italic; color: #06d6a0;">"Que la magia de la temporada llene tu hogar de alegría, tu corazón de amor y tu vida de felicidad."</p>
                </div>
                
                <p style="color: #555; line-height: 1.6;">Agradecemos tu confianza durante este año y esperamos continuar sirviéndote en el próximo.</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="#" style="background: #06d6a0; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block;">Enviar Saludos</a>
                </div>
                
                <p style="color: #555; line-height: 1.6;">¡Felices fiestas y próspero año nuevo!</p>
            </div>
            <div style="text-align: center; padding: 20px; color: #777; font-size: 14px;">
                <p>© 2023 KABZO. Todos los derechos reservados.</p>
            </div>
        </div>`,
        
        'minimal': `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;">
            <div style="background: white; padding: 30px; border-radius: 10px; border-top: 5px solid #6a4c93;">
                <h1 style="color: #333; text-align: center; margin-top: 0;">Mensaje Importante</h1>
                
                <h2 style="color: #6a4c93; margin-top: 20px;">Hola {{name}},</h2>
                <p style="color: #555; line-height: 1.6;">Este es un mensaje claro y directo para informarte sobre un tema importante.</p>
                
                <div style="background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p style="margin: 0; color: #555;">Tu dirección de correo registrada es: <strong>{{email}}</strong></p>
                </div>
                
                <p style="color: #555; line-height: 1.6;">Si necesitas más información, no dudes en contactarnos.</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="#" style="background: #6a4c93; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">Más Información</a>
                </div>
                
                <p style="color: #777; font-size: 14px; text-align: center;">Atentamente,<br>El equipo</p>
            </div>
        </div>`,
        
        'elegant': `<div style="font-family: 'Georgia', serif; max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 20px;">
            <div style="background: #2b2d42; color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-weight: normal; letter-spacing: 2px;">INVITACIÓN FORMAL</h1>
                <p style="margin: 10px 0 0; opacity: 0.8; font-style: italic;">Una ocasión especial</p>
            </div>
            <div style="background: white; padding: 40px 30px; border-bottom: 1px solid #eaeaea;">
                <h2 style="color: #2b2d42; margin-top: 0; font-weight: normal;">Estimado/a {{name}},</h2>
                <p style="color: #555; line-height: 1.8; font-size: 16px;">Es un honor para nosotros extenderle esta invitación formal a nuestro evento exclusivo que se llevará a cabo el próximo mes.</p>
                
                <div style="border-left: 3px solid #2b2d42; padding-left: 20px; margin: 25px 0;">
                    <p style="color: #2b2d42; font-style: italic; margin: 0;">"La excelencia no es un acto, sino un hábito"</p>
                </div>
                
                <p style="color: #555; line-height: 1.8; font-size: 16px;">Confiamos en que esta será una experiencia enriquecedora y esperamos contar con su distinguida presencia.</p>
                
                <div style="text-align: center; margin: 40px 0 20px;">
                    <a href="#" style="background: #2b2d42; color: white; padding: 12px 30px; text-decoration: none; border-radius: 0; display: inline-block; letter-spacing: 1px; font-size: 14px;">CONFIRMAR ASISTENCIA</a>
                </div>
            </div>
            <div style="background: #f5f5f5; padding: 20px; text-align: center; color: #777; font-size: 14px;">
                <p style="margin: 0; font-style: italic;">Atentamente,<br>La Dirección</p>
            </div>
        </div>`,
        
        'custom': `<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2 style="color: #333;">Hola {{name}},</h2>
            <p>Este es un correo personalizado. Tu dirección de correo es: {{email}}</p>
            <p>Puedes editar este contenido como desees usando el editor.</p>
        </div>`
    };

    // Sistema de pestañas
    $('.tab').click(function() {
        const tabId = $(this).data('tab');
        
        $('.tab').removeClass('active');
        $(this).addClass('active');
        
        $('.tab-content').removeClass('active');
        $(`#${tabId}-tab`).addClass('active');
    });

    // Navegación entre pestañas
    $('.next-tab').click(function() {
        const nextTab = $(this).data('next');
        
        $('.tab').removeClass('active');
        $(`.tab[data-tab="${nextTab}"]`).addClass('active');
        
        $('.tab-content').removeClass('active');
        $(`#${nextTab}-tab`).addClass('active');
        
        // Actualizar vista previa si vamos a esa pestaña
        if(nextTab === 'preview') {
            updatePreview();
        }
        
        // Actualizar resumen si vamos a la pestaña de envío
        if(nextTab === 'send') {
            updateSummary();
        }
    });

    $('.prev-tab').click(function() {
        const prevTab = $(this).data('prev');
        
        $('.tab').removeClass('active');
        $(`.tab[data-tab="${prevTab}"]`).addClass('active');
        
        $('.tab-content').removeClass('active');
        $(`#${prevTab}-tab`).addClass('active');
    });

    // Selección de plantillas
    $('.template-card').click(function() {
        $('.template-card').removeClass('active');
        $(this).addClass('active');
        
        const templateId = $(this).data('template');
        $('#body').summernote('code', templates[templateId]);
        
        // Actualizar vista previa si estamos en esa pestaña
        if($('#preview-tab').hasClass('active')) {
            updatePreview();
        }
    });

    // Validar emails
    $('#validateEmails').click(function() {
        const emailsText = $('#recipients').val();
        const emails = emailsText.split(/[\n,]+/).map(email => email.trim()).filter(email => email);
        
        let validCount = 0;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        emails.forEach(email => {
            // Extraer solo el email si está en formato "Nombre <email>"
            const match = email.match(/<(.+)>/);
            const actualEmail = match ? match[1] : email;
            
            if(emailRegex.test(actualEmail)) {
                validCount++;
            }
        });
        
        $('#emailCount').text(`${validCount} emails válidos`).show();
        
        if(validCount === emails.length) {
            $('#emailCount').addClass('badge-success').removeClass('badge-danger');
        } else {
            $('#emailCount').addClass('badge-danger').removeClass('badge-success');
        }
    });

    // Actualizar vista previa
    function updatePreview() {
        const subject = $('#subject').val();
        const body = $('#body').summernote('code');
        
        $('#preview-subject').text(subject);
        $('#emailPreview').html(body);
    }

    // Actualizar resumen
    function updateSummary() {
        const emailsText = $('#recipients').val();
        const emails = emailsText.split(/[\n,]+/).map(email => email.trim()).filter(email => email);
        
        let validCount = 0;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        emails.forEach(email => {
            const match = email.match(/<(.+)>/);
            const actualEmail = match ? match[1] : email;
            
            if(emailRegex.test(actualEmail)) {
                validCount++;
            }
        });
        
        const selectedTemplate = $('.template-card.active').find('.template-name').text() || 'Personalizada';
        
        $('#summary-text').text(`Se enviarán ${validCount} correos con la plantilla "${selectedTemplate}"`);
    }

    // Actualizar vista previa cuando cambie el asunto o el cuerpo
    $('#subject').on('input', function() {
        if($('#preview-tab').hasClass('active')) {
            updatePreview();
        }
    });

    $('#refreshPreview').click(updatePreview);

    // Establecer plantilla por defecto
    $('.template-card[data-template="business"]').click();
});
</script>
</body>
</html>