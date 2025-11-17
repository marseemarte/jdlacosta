<?php

       $sorteo=326;

        include('../conexion.php');

        $sqlsecundarias="SELECT id, vacantes FROM secundarias";
        $i=1;
        $consultasecundarias =  mysqli_query($conexion,$sqlsecundarias);
        while($vecsecundarias=mysqli_fetch_row($consultasecundarias)){
            $id = $vecsecundarias[0];
            $vacantes=$vecsecundarias[1];
                $sql="SELECT alumnos.dni, alumnos.apellido, alumnos.nombre, alumnos.fecha, alumnos.direccion, alumnos.vinculo, alumnos.escuela, alumnos.comprobado, alumnos.dni_hermano, alumnos.curso, alumnos.dni_personal, alumnos.turno, padres.dni, padres.apellido, padres.nombre, padres.fecha, padres.telefono, padres.mail, alumnos.id, alumnos.matricula FROM alumnos
                INNER JOIN padrealumno ON alumnos.id=padrealumno.dni_alumno
                INNER JOIN padres ON padrealumno.dni_padre=padres.id
                WHERE alumnos.id_secundaria='$id' AND comprobado ='1'";
                $noentran=0;
            $consulta =  mysqli_query($conexion,$sql);
            if(mysqli_num_rows($consulta)>0){
                $cont=1;
                while($vec=mysqli_fetch_row($consulta)){
                    $queryM = "UPDATE alumnos SET entro = '1' WHERE id = '$vec[18]'";
                    //echo "$i - ENTRO $vec[0]<BR>";
                    $resultadoM = mysqli_query($conexion, $queryM);
                    echo "$queryM ;<BR>";
                    $cont++;
                    $i++;
                }
            }

            $sql="SELECT alumnos.dni, alumnos.apellido, alumnos.nombre, alumnos.fecha, alumnos.direccion, alumnos.vinculo, alumnos.escuela, alumnos.comprobado, alumnos.dni_hermano, alumnos.curso, 
            alumnos.dni_personal, alumnos.turno, padres.dni, padres.apellido, padres.nombre, padres.fecha, padres.telefono, padres.mail, alumnos.id, alumnos.matricula FROM alumnos
                INNER JOIN padrealumno ON alumnos.id=padrealumno.dni_alumno
                INNER JOIN padres ON padrealumno.dni_padre=padres.id
                INNER JOIN sorteo ON sorteo.dni = alumnos.dni
                WHERE alumnos.id_secundaria='$id' AND comprobado ='0' AND ultimos3 >= $sorteo order by ultimos3";
            $consulta =  mysqli_query($conexion,$sql);
            $hijos = $cont-1;
            $entran = $vacantes - $hijos;
            $cont=1;
            if(mysqli_num_rows($consulta)>0){
                while($vec=mysqli_fetch_row($consulta)){
                    if($cont <= $entran){
                        $queryM = "UPDATE alumnos SET entro = '1' WHERE id = '$vec[18]'";
                        $resultadoM = mysqli_query($conexion, $queryM);
                        echo "$queryM ;<BR>";
                        //echo "$i - ENTRO $vec[0]<BR>";
                        $i++;
                    }else{
                        $noentran++;
                        $queryM = "UPDATE alumnos SET entro = '2', espera = '$noentran' WHERE id = '$vec[18]'";
                        //echo "$i - NO ENTRO $vec[0] - Espera $noentran<BR>";
                        echo "$queryM ;<BR>";
                        $resultadoM = mysqli_query($conexion, $queryM);
                        $i++;
                    }
                    $cont++;
                }
            }
            if ($entran > $cont){
                $sql="SELECT alumnos.dni, alumnos.apellido, alumnos.nombre, alumnos.fecha, alumnos.direccion, alumnos.vinculo, alumnos.escuela, alumnos.comprobado, alumnos.dni_hermano, alumnos.curso, 
                alumnos.dni_personal, alumnos.turno, padres.dni, padres.apellido, padres.nombre, padres.fecha, padres.telefono, padres.mail, alumnos.id, alumnos.matricula FROM alumnos
                    INNER JOIN padrealumno ON alumnos.id=padrealumno.dni_alumno
                    INNER JOIN padres ON padrealumno.dni_padre=padres.id
                    INNER JOIN sorteo ON sorteo.dni = alumnos.dni
                    WHERE alumnos.id_secundaria='$id' AND alumnos.comprobado ='0' AND sorteo.ultimos3 < $sorteo AND sorteo.ultimos3 > '0' order by sorteo.ultimos3";
                $consulta =  mysqli_query($conexion,$sql);
                if(mysqli_num_rows($consulta)>0){
                    while($vec=mysqli_fetch_row($consulta)){
                        if($cont <= $entran){
                            $queryM = "UPDATE alumnos SET entro = '1' WHERE id = '$vec[18]'";
                            $resultadoM = mysqli_query($conexion, $queryM);
                            echo "$queryM;<BR>";
                            //echo "$i - ENTRO $vec[0]<BR>";
                            $i++;
                        }else{
                            $noentran++;
                            $queryM = "UPDATE alumnos SET entro = '2', espera = '$noentran' WHERE id = '$vec[18]'";
                            //echo "$i - NO ENTRO $vec[0] - Espera $noentran<BR>";
                            echo "$queryM ;<BR>";
                            $resultadoM = mysqli_query($conexion, $queryM);
                            $i++;
                        }
                        $cont++;
                    }
                }
            } 
        }











?>



