<?php
namespace Alwaysstone\YAMLReader;

require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use \Symfony\Component\Yaml\Exception\ParseException;
use Alwaysstone\YAMLReader\YAMLReaderException;

class YAMLReaderTest extends TestCase {
    public function testYAMLLoad(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';
        $yaml_result_file = __DIR__ . '/../../result.yml';
        $this->assertFileExists($yaml_test_file, "Cannot find test.yml test file");
        
        $yaml = new YAMLReader();
        $this->assertFalse($yaml->isReady());
        $yaml->loadFile($yaml_test_file);
        $this->assertTrue($yaml->isReady());
        $yaml->save($yaml_result_file);
        $this->assertFileExists($yaml_result_file, "Test result file cannot be created");
        $this->assertFileEquals($yaml_test_file, $yaml_result_file, "File differs, just wrong");
        
        $yaml2 = new YAMLReader($yaml_test_file);
        $this->assertTrue($yaml2->isReady());
        $yaml2->save($yaml_result_file);
        $this->assertFileExists($yaml_result_file, "Test result file cannot be created");
        $this->assertFileEquals($yaml_test_file, $yaml_result_file, "File differs, just wrong");
        
        $yaml3 = new YAMLReader();
        $yaml3->loadString(file_get_contents($yaml_result_file));
        $this->assertTrue($yaml3->isReady());
        $yaml3->save($yaml_result_file);
        $this->assertFileExists($yaml_result_file, "Test result file cannot be created");
        $this->assertFileEquals($yaml_test_file, $yaml_result_file, "File differs, just wrong");
    }
    
    public function testYAMLMove(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';
        
        $this->assertFileExists($yaml_test_file, "Cannot find test.yml test file");
        
        $yaml = new YAMLReader($yaml_test_file);
        
        $currSardegnaPos = $yaml->findPosition("/italia/regioni/sardegna");
        $this->assertEquals(0, $currSardegnaPos);
        
        $yaml->move("/italia/regioni/sardegna", "/italia/regioni", -1);
        $currSardegnaPos = $yaml->findPosition("/italia/regioni/sardegna");
        $this->assertEquals(2, $currSardegnaPos);
        
        $yaml->move("/italia/regioni/sardegna", "/italia/regioni", 0);
        $currSardegnaPos = $yaml->findPosition("/italia/regioni/sardegna");
        $this->assertEquals(0, $currSardegnaPos);
        
        $yaml->move("/italia/regioni/sardegna", "/italia/regioni", "abruzzo");
        $currSardegnaPos = $yaml->findPosition("/italia/regioni/sardegna");
        $this->assertEquals(1, $currSardegnaPos);
        
        $yaml->move("/italia/regioni/sardegna", "/italia/regioni", "b:piemonte");
        $currSardegnaPos = $yaml->findPosition("/italia/regioni/sardegna");
        $this->assertEquals(1, $currSardegnaPos);
        
        $yaml->move("/italia/regioni/sardegna", "/italia/regioni", "piemonte");
        $currSardegnaPos = $yaml->findPosition("/italia/regioni/sardegna");
        $this->assertEquals(2, $currSardegnaPos);
        
        $yaml->move("/italia/regioni/sardegna", "/italia/regioni", "b:abruzzo");
        $currSardegnaPos = $yaml->findPosition("/italia/regioni/sardegna");
        $this->assertEquals(0, $currSardegnaPos);
        
        $yaml->move("/italia/regioni/sardegna", "/italia/regioni", 0);
        $yaml->move("/italia/regioni/sardegna", "/italia/regioni", "abruzzo");
        $currSardegnaPos = $yaml->findPosition("/italia/regioni/sardegna");
        $currAbruzzoPos = $yaml->findPosition("/italia/regioni/abruzzo");
        $this->assertGreaterThan($currAbruzzoPos, $currSardegnaPos);
        
        $yaml->move("/italia/regioni/sardegna", "/italia/regioni", 0);
        $yaml->move("/italia/regioni/sardegna", "/italia/regioni", "b:piemonte");
        $currSardegnaPos = $yaml->findPosition("/italia/regioni/sardegna");
        $currAbruzzoPos = $yaml->findPosition("/italia/regioni/piemonte");
        $this->assertGreaterThan($currSardegnaPos,$currAbruzzoPos);
        
        $this->assertFalse($yaml->isPresent("/italia/regioni/sardegna/province/cagliari/comuni/bosa"));
        $this->assertTrue($yaml->isPresent("/italia/regioni/sardegna/province/oristano/comuni/bosa"));
        $yaml->move("/italia/regioni/sardegna/province/oristano/comuni/bosa", "/italia/regioni/sardegna/province/cagliari/comuni", -1);
        $this->assertTrue($yaml->isPresent("/italia/regioni/sardegna/province/cagliari/comuni/bosa"));
        $this->assertFalse($yaml->isPresent("/italia/regioni/sardegna/province/oristano/comuni/bosa"));
        
    }
    
    public function testYAMLCreate(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';
        
        $this->assertFileExists($yaml_test_file, "Cannot find test.yml test file");
        
        $yaml = new YAMLReader($yaml_test_file);
        $this->assertFalse($yaml->isPresent("/italia/regioni/sardegna/province/sassari"));
        
        $yaml->create("/italia/regioni/sardegna/province/sassari", ['abitanti' => 2222, 'comuni' => [] ]);
        
        $this->assertTrue($yaml->isPresent("/italia/regioni/sardegna/province/sassari"));
    }
    
    public function testYAMLExtract(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';
        $yaml_result_file = __DIR__ . '/../../sardegna.yml';
        
        
        
        $this->assertFileExists($yaml_test_file, "Cannot find test.yml test file");
        
        $yaml = new YAMLReader($yaml_test_file);
        
        $newYaml = $yaml->extract("/italia/regioni/sardegna");
        
        $newYaml->save($yaml_result_file);
        
        $this->assertFileExists($yaml_result_file);
        $content = <<<SARDYAML
sardegna:
  codice: IT_89
  abitanti: 1000222
  province:
    cagliari:
      codice: CA
      abitanti: 10222
      comuni:
        cagliari:
          codice: B354
          abitanti: 1222
        assemini:
          codice: A474
          abitanti: 1223
    oristano:
      codice: OR
      abitanti: 20222
      comuni:
        bosa:
          codice: B354
          abitanti: 2222
        oristano:
          codice: A474
          abitanti: 2223
SARDYAML; 
        $content = trim($content);
        
        $this->assertStringEqualsFile($yaml_result_file, $content);
        
    }

    public function testYAMLDelete(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';
        
        $this->assertFileExists($yaml_test_file, "Cannot find test.yml test file");
        
        $yaml = new YAMLReader($yaml_test_file);
        $this->assertTrue($yaml->isPresent("/italia/regioni/sardegna/province/cagliari"));
        
        $yaml->delete("/italia/regioni/sardegna/province/cagliari");
        
        $this->assertFalse($yaml->isPresent("/italia/regioni/sardegna/province/cagliari"));
    }
    
    public function testYAMLData(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';
        
        $this->assertFileExists($yaml_test_file, "Cannot find test.yml test file");
        
        $yaml = new YAMLReader($yaml_test_file);
        
        $abitanti = $yaml->valueAt("/italia/regioni/sardegna/province/cagliari/abitanti");
        
        $this->assertEquals(10222, $abitanti);
        
        $yaml->update("/italia/regioni/sardegna/province/cagliari/abitanti", 203334);
        $abitanti = $yaml->valueAt("/italia/regioni/sardegna/province/cagliari/abitanti");
        $this->assertEquals(203334, $abitanti);
    }
    
    public function testYAMLSave(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';
        $yaml_result_file = __DIR__ . '/../../result.yml';
        
        unlink($yaml_result_file);
        
        $this->assertFileExists($yaml_test_file, "Cannot find test.yml test file");
        $this->assertFileDoesNotExist($yaml_result_file, "Cannot delete result.yml test file");
        $yaml = new YAMLReader($yaml_test_file);
        
        $yaml->delete("/italia/regioni/sardegna/province/cagliari");
        
        $yaml->save($yaml_result_file);
        
        $this->assertFileExists($yaml_result_file, "Cannot find result.yml test file");
        $this->assertFileNotEquals($yaml_result_file, $yaml_test_file, "Same file saved, that's a problem");
    }
    
    public function testYAMLParseExceptionConstruct(): void {
        $yaml_test_file = __DIR__ . '/../../test_error.yml';
        $this->expectException(ParseException::class);
        
        $yaml = new YAMLReader($yaml_test_file);
    }
    
    public function testYAMLParseExceptionLoad(): void {
        $yaml_test_file = __DIR__ . '/../../test_NONO.yml';
        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader();
        $yaml->loadFile($yaml_test_file);
    }
    
    public function testYAMLMoveNotReady(): void {
        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader();
        $yaml->move("/italia/regioni/sardegna/province/oristano/comuni/bosa", "/italia/regioni/sardegna/province/cagliari/comuni", -1);
    }
    
    public function testYAMLDeleteNotReady(): void {
        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader();
        $yaml->delete("/italia/regioni/sardegna/province/oristano/comuni/bosa");
    }
    
    public function testYAMLPositionNotReady(): void {
        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader();
        $yaml->findPosition("/italia/regioni/sardegna/province/oristano/comuni/bosa");
    }
    
    public function testYAMLisPresentNotReady(): void {
        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader();
        $yaml->isPresent("/italia/regioni/sardegna/province/oristano/comuni/bosa");
    }
    
    public function testYAMLupdateNotReady(): void {
        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader();
        $yaml->update("/italia/regioni/sardegna/province/oristano/comuni/bosa/abitanti", 2000);
    }
    
    public function testYAMLvalueAtNotReady(): void {
        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader();
        $yaml->valueAt("/italia/regioni/sardegna/province/oristano/comuni/bosa/abitanti");
    }
    
    public function testYAMLextractNotReady(): void {
        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader();
        $yaml->extract("/italia/regioni/sardegna/province/oristano/comuni");
    }
    
    public function testYAMLcreateNotReady(): void {
        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader();
        $yaml->create("/italia/regioni/sardegna/province/sassari", ['abitanti' => 2222, 'comuni' => [] ]);
    }

    public function testYAMLsaveNotReady(): void {
        $yaml_result_file = __DIR__ . '/../../result.yml';
        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader();
        $yaml->save($yaml_result_file);
    }
    
    public function testYAMLMoveNoSourcePath(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';

        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader($yaml_test_file);
        $yaml->move("/francia/regioni/sardegna", "/italia/regioni", -1);
    }
    
    public function testYAMLMoveNoTargetPath(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';

        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader($yaml_test_file);
        $yaml->move("/italia/regioni/sardegna", "/francia/regioni", -1);
    }
    
    public function testYAMLUpdateNoSourcePath(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';

        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader($yaml_test_file);
        $yaml->update("/francia/regioni/sardegna/abitanti", 100000);
    }
    
    public function testYAMLValueAtNoSourcePath(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';

        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader($yaml_test_file);
        $yaml->valueAt("/francia/regioni/sardegna/abitanti");
    }
    
    public function testYAMLExctractNoSourcePath(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';

        $this->expectException(YAMLReaderException::class);
        
        $yaml = new YAMLReader($yaml_test_file);
        $yaml->extract("/francia/regioni/sardegna");
    }

    public function testYAMLExctractItemWithSpace(): void {
        $yaml_test_file = __DIR__ . '/../../test.yml';
        $yaml_result_file = __DIR__ . '/../../acqui_terme.yml';
        
        $yaml = new YAMLReader($yaml_test_file);
        $newYaml = $yaml->extract("/italia/regioni/piemonte/province/alessandria/comuni/acqui terme");

        $newYaml->save($yaml_result_file);
        $this->assertFileExists($yaml_result_file, "Cannot find test.yml test file");
        $content = <<<SARDYAML
acqui terme:
  codice: YYYYYY
  abitanti: 4445
SARDYAML; 
        $content = trim($content);
        $this->assertStringEqualsFile($yaml_result_file, $content);

        $this->assertFalse($yaml->isPresent("/italia/regioni/sardegna/province/cagliari/comuni/acqui terme"));
        $this->assertTrue($yaml->isPresent("/italia/regioni/piemonte/province/alessandria/comuni/acqui terme"));
        $yaml->move("/italia/regioni/piemonte/province/alessandria/comuni/acqui terme", "/italia/regioni/sardegna/province/cagliari/comuni", -1);
        $this->assertTrue($yaml->isPresent("/italia/regioni/sardegna/province/cagliari/comuni/acqui terme"));
        $this->assertFalse($yaml->isPresent("/italia/regioni/piemonte/province/alessandria/comuni/acqui terme"));

        $this->assertFalse($yaml->isPresent("/italia/regioni/sardegna/province/sassari/"));
        
        $yaml->create("/italia/regioni/sardegna/province/sassari", [
            'abitanti' => 2222, 
            'comuni' => [
                'test spazio' => [
                    'codice' => 'NNNNNNNNN',
                    'abitanti' => 2776
                ],
                'Portotorres' => [
                    'codice' => 'PRTTRRS',
                    'abitanti' => 78758
                ]
            ] 
        ]);
        
        $this->assertTrue($yaml->isPresent("/italia/regioni/sardegna/province/sassari/"));
        $newYaml = $yaml->extract("/italia/regioni/sardegna/province/sassari");
        $newYaml->save($yaml_result_file);
        $content = <<<SARDYAML
sassari:
  abitanti: 2222
  comuni:
    test spazio:
      codice: NNNNNNNNN
      abitanti: 2776
    Portotorres:
      codice: PRTTRRS
      abitanti: 78758
SARDYAML;
        $content = trim($content);
        $this->assertStringEqualsFile($yaml_result_file, $content);

        $abitanti = $yaml->valueAt("/italia/regioni/sardegna/province/sassari/comuni/test spazio/abitanti");
        
        $this->assertEquals(2776, $abitanti);

        $yaml->update("/italia/regioni/sardegna/province/sassari/comuni/test spazio/abitanti", 203334);
        $abitanti = $yaml->valueAt("/italia/regioni/sardegna/province/sassari/comuni/test spazio/abitanti");
        $this->assertEquals(203334, $abitanti);

        $this->assertTrue($yaml->isPresent("/italia/regioni/sardegna/province/sassari/comuni/test spazio"));
        
        $yaml->delete("/italia/regioni/sardegna/province/sassari/comuni/test spazio");
        
        $this->assertFalse($yaml->isPresent("/italia/regioni/sardegna/province/sassari/comuni/test spazio"));

        
    }

}

